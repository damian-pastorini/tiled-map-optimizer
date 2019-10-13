<?php
// get config:
require_once('config.php');
// shortcut:
define('DS', DIRECTORY_SEPARATOR);

/**
 * Class Processor
 */
class Processor
{

    protected $createUrl;
    protected $newName;
    protected $transparentColor;
    protected $tileWidth;
    protected $tileHeight;
    protected $totalRows;
    protected $totalColumns;
    protected $newMapImageWidth;
    protected $newMapImageHeight;
    protected $newMapImage;
    protected $mappedOldToNewTiles = [];
    protected $tileSetData = [];
    protected $uploadedImages = [];
    protected $newImagesPositions = [];
    protected $baseDir;
    protected $createDir;
    protected $colorR;
    protected $colorG;
    protected $colorB;
    protected $output = '';

    /**
     * @param $json
     * @param $images
     * @param string $newName
     * @param string $transparentColor
     * @param bool $factors
     * @return string
     */
    public function optimize(
        $json,
        $images,
        $newName = 'optimizedMap',
        $transparentColor = '#000000',
        $factors = false // string of comma separated values.
    ){
        $this->createAndConfigureFolders();
        $this->createUrl = (defined('OPTIMIZER_URL') ? OPTIMIZER_URL : '').'/created/';
        $this->newName = $newName;
        $this->transparentColor = $transparentColor;
        $this->tileWidth = $json->tilewidth;
        $this->tileHeight = $json->tileheight;
        $this->orderImages($images);
        $this->parseJSON($json);
        $this->colorR = $this->colorG = $this->colorB = 0;
        $newMapImage = imagecreatetruecolor($this->newMapImageWidth, $this->newMapImageHeight);
        imagesavealpha($newMapImage, true);
        //create a fully transparent background (127 means fully transparent):
        $transBackground = imagecolorallocatealpha($newMapImage, 0, 0, 0, 127);
        // fill the image with a transparent background:
        imagefill($newMapImage, 0, 0, $transBackground);
        $this->newMapImage = $newMapImage;
        $this->createThumbsFromLayersData();
        $this->createNewJSON($json);
        if($factors){
            $this->resizeTileset($factors);
        }
        return $this->output;
    }

    protected function createAndConfigureFolders()
    {
        $this->baseDir = dirname(__FILE__).DS;
        $this->createDir = $this->baseDir.'created'.DS;
        if(!is_dir($this->createDir)){
            mkdir($this->createDir, 0775);
        }
    }

    /**
     * @param $images
     */
    protected function orderImages($images)
    {
        foreach ($images['name'] as $key => $imageName){
            $this->uploadedImages[$key] = [
                'name' => $imageName,
                'tmp_name' => $images['tmp_name'][$key]
            ];
        }
    }

    /**
     * @param $json
     */
    protected function parseJSON($json)
    {
        // loop over layers data:
        foreach ($json->layers as $layer){
            // check layer data:
            if(!$layer->data){
                die('ERROR CODE - 2 - Invalid JSON.');
            }
            // clean up for duplicates:
            $clean = array_unique($layer->data);
            // map new positions:
            $this->mappedOldToNewTiles = array_merge($this->mappedOldToNewTiles, $clean);
        }
        // get tilesets data:
        foreach ($json->tilesets as $tileset){
            // get tiles used for animations:
            $animations = [];
            $animationTiles = [];
            if(isset($tileset->tiles)){
                $animations = array_merge($animations, $tileset->tiles);
                foreach($tileset->tiles as $animation){
                    $animationTiles[] = $tileset->firstgid + $animation->id;
                    foreach($animation->animation as $frame){
                        $animationTiles[] = $tileset->firstgid + $frame->tileid;
                    }
                }
            }
            $cleanAnimationTiles = array_unique($animationTiles);
            // merge in the map array:
            $this->mappedOldToNewTiles = array_merge($this->mappedOldToNewTiles, $cleanAnimationTiles);
            // parse tileset data:
            $tilesetImagePathArray = explode('/', $tileset->image);
            $tilesetImageName = end($tilesetImagePathArray);
            $this->tileSetData[$tileset->name] = [
                'first' => $tileset->firstgid,
                'last' => ($tileset->firstgid + $tileset->tilecount),
                'tiles_count' => $tileset->tilecount,
                'image' => $tilesetImageName,
                'tmp_image' => $this->getTempImageByName($tilesetImageName),
                'width' => $tileset->imagewidth,
                'height' => $tileset->imageheight,
                'animations' => $animations
            ];
        }
        // clean up duplicates:
        $this->mappedOldToNewTiles = array_unique($this->mappedOldToNewTiles);
        // sort:
        sort($this->mappedOldToNewTiles);
        // remove zero:
        array_shift($this->mappedOldToNewTiles);
        // calculate new map image size:
        $totalTiles = count($this->mappedOldToNewTiles);
        $this->totalColumns = ceil(sqrt($totalTiles));
        $this->newMapImageWidth = $this->totalColumns * $this->tileWidth + $this->tileWidth;
        $this->totalRows = ceil($totalTiles / $this->totalColumns);
        $this->newMapImageHeight = $this->totalRows * $this->tileHeight;
    }

    /**
     * @param $tilesetImageName
     * @return bool|mixed
     */
    protected function getTempImageByName($tilesetImageName)
    {
        $result = false;
        foreach ($this->uploadedImages as $uploadedImage){
            if($uploadedImage['name'] == $tilesetImageName){
                $result = $uploadedImage['tmp_name'];
            }
        }
        if(!$result){
            die('ERROR - The specified image in the tileset was not found: '.$tilesetImageName);
        }
        return $result;
    }

    /**
     * @param $baseImage
     * @param $tileX
     * @param $tileY
     * @return bool|resource
     */
    protected function createSingleTileImage($baseImage, $tileX, $tileY)
    {
        $im = imagecreatefrompng($baseImage);
        $tileData = [
            'x' => $tileX,
            'y' => $tileY,
            'width' => $this->tileWidth,
            'height' => $this->tileHeight
        ];
        $tileImage = imagecrop($im, $tileData);
        return $tileImage;
    }

    protected function createThumbsFromLayersData()
    {
        $tilesRowCounter = 0;
        $tilesColCounter = 0;
        foreach ($this->mappedOldToNewTiles as $newTileIndex => $mappedTileIndex){
            if($tilesRowCounter > 0 && $tilesRowCounter == $this->totalColumns){
                $tilesRowCounter = 0;
                $tilesColCounter++;
            } else {
                $tilesRowCounter++;
            }
            $tileset = $this->getTileSetByTileIndex($mappedTileIndex);
            $tilePosition = $this->getTilePositionFromTilesetData($tileset, $mappedTileIndex);
            $newImagePosition = (($this->totalColumns + 1) * $tilesColCounter) + $tilesRowCounter + 1;
            $singleTileImage = $this->createSingleTileImage($tileset['tmp_image'], $tilePosition['x'], $tilePosition['y']);
            if($singleTileImage){
                $destX = $tilesRowCounter * $this->tileWidth;
                $destY = $tilesColCounter * $this->tileHeight;
                // @NOTE: x and y for the origin positions are always 0 since we are using new and just cropped images.
                imagecopy($this->newMapImage, $singleTileImage, $destX, $destY, 0, 0, $this->tileWidth, $this->tileHeight);
                $this->newImagesPositions[$mappedTileIndex] = $newImagePosition;
            } else {
                die('ERROR - Tile image could not be created.');
            }
        }
        imagepng($this->newMapImage, $this->createDir.$this->newName.'.png');
        chmod($this->createDir.$this->newName.'.png', 0775);
        $this->output .= '<div class="col-12 mb-3">'
            .'<h2>Download your optimized JSON and image map file!</h2>'
            .'</div>'
            .'<div class="col-12 mb-3">'
            .'<a href="'.$this->createUrl.$this->newName.'.json">New JSON Map File</a>'
            .'</div>'
            .'<div class="col-12 mb-3">'
            .'<a href="'.$this->createUrl.$this->newName.'.png">'
            .'<img src="'.$this->createUrl.$this->newName.'.png"/>'
            .'</a>'
            .'</div>';
        imagedestroy($this->newMapImage);
    }

    /**
     * @param $tileset
     * @param $mappedTileIndex
     * @return array|bool
     */
    protected function getTilePositionFromTilesetData($tileset, $mappedTileIndex)
    {
        $result = false;
        $totalColumns = $tileset['width'] / $this->tileWidth;
        $totalRows = $tileset['height'] / $this->tileHeight;
        $tilesCounter = 0;
        for ($r=0; $r<$totalRows; $r++){
            for ($c=0; $c<$totalColumns; $c++) {
                $mapIndex = $tilesCounter + $tileset['first'];
                if($mapIndex == $mappedTileIndex){
                    $posX = $c * $this->tileWidth;
                    $posY = $r * $this->tileHeight;
                    $result = ['x' => $posX, 'y' => $posY];
                    break;
                }
                $tilesCounter++;
            }
            if($result){
                break;
            }
        }
        return $result;
    }

    /**
     * @param $mappedTileIndex
     * @return bool|mixed
     */
    protected function getTileSetByTileIndex($mappedTileIndex)
    {
        $result = false;
        foreach ($this->tileSetData as $tileSet){
            if($mappedTileIndex >= $tileSet['first'] && $mappedTileIndex <= $tileSet['last']){
                $result = $tileSet;
            }
        }
        if(!$result){
            die('ERROR - $mappedTileIndex not found');
        }
        return $result;
    }

    /**
     * @param $json
     */
    protected function createNewJSON($json)
    {
        foreach ($json->layers as $layer){
            foreach ($layer->data as $k => $data){
                if($data !== 0){
                    $layer->data[$k] = $this->newImagesPositions[$data];
                }
            }
        }
        $animations = [];
        foreach($this->tileSetData as $tileset){
            foreach($tileset['animations'] as $animation){
                $tmpAnimObj = new stdClass();
                $tmpAnimObj->animation = [];
                $tmpAnimObj->id = $this->newImagesPositions[($tileset['first']+$animation->id)];
                foreach($animation->animation as $frame){
                    $tmpFrame = new stdClass();
                    $tmpFrame->duration = $frame->duration;
                    $tmpFrame->tileid = $this->newImagesPositions[($tileset['first']+$frame->tileid)];
                    $tmpAnimObj->animation[] = $tmpFrame;
                }
                $animations[] = $tmpAnimObj;
            }
        }
        $newTileSet = new stdClass();
        $newTileSet->columns = $this->totalColumns;
        $newTileSet->firstgid = 1;
        $newTileSet->image = $this->newName.'.png';
        $newTileSet->imageheight = $this->newMapImageHeight;
        $newTileSet->imagewidth = $this->newMapImageWidth;
        $newTileSet->margin = 0;
        $newTileSet->name = strtolower($this->newName);
        $newTileSet->spacing = 0;
        $newTileSet->tilecount = $this->totalRows * $this->totalColumns;
        $newTileSet->tileheight = $this->tileWidth;
        $newTileSet->tilewidth = $this->tileHeight;
        $newTileSet->transparentcolor = $this->transparentColor;
        if(!empty($animations)){
            $newTileSet->tiles = $animations;
        }
        $json->tilesets = [$newTileSet];
        $save = fopen($this->createDir.$this->newName.'.json', 'w');
        fwrite($save, json_encode($json));
        fclose($save);
        chmod($this->createDir.$this->newName.'.json', 0775);
    }

    /**
     * @param string $factors
     */
    public function resizeTileset($factors = '2')
    {
        // multipliers:
        $multipliers = explode(',', $factors);
        // original files data:
        $originalTilesetImage = $this->createDir.DS.$this->newName.'.png';
        $originalTilsetJson = $this->createDir.DS.$this->newName.'.json';
        foreach($multipliers as $multiplier){
            // new files names:
            $resizedImageName = $this->newName.'-x'.$multiplier.'.png';
            $resizedJsonName = $this->newName.'-x'.$multiplier.'.json';
            // get new sizes
            list($width, $height) = getimagesize($originalTilesetImage);
            $newWidth = $width * $multiplier;
            $newHeight = $height * $multiplier;
            // load
            $thumb = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($thumb, true);
            $source = imagecreatefrompng($originalTilesetImage);
            //create a fully transparent background (127 means fully transparent):
            $trans_background = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            // fill the image with a transparent background:
            imagefill($thumb, 0, 0, $trans_background);
            // resize
            imagecopyresized($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            // output
            imagepng($thumb, $this->createDir.DS.$resizedImageName);
            // process original json:
            $json = json_decode(file_get_contents($originalTilsetJson));
            // modify values:
            $json->tilewidth = $json->tilewidth * $multiplier;
            $json->tileheight = $json->tileheight * $multiplier;
            foreach ($json->tilesets as $tileset){
                $tileset->tilewidth = $tileset->tilewidth * $multiplier;
                $tileset->tileheight = $tileset->tileheight * $multiplier;
                $tileset->image = $resizedImageName;
                $tileset->imagewidth = $newWidth;
                $tileset->imageheight = $newHeight;
            }
            // save new json
            $save = fopen($this->createDir.DS.$resizedJsonName, 'w');
            fwrite($save, json_encode($json));
            fclose($save);
            // print result:
            $this->output .= '<div class="col-12 mb-3">'
                .'<a href="'.$this->createUrl.$resizedJsonName.'">Download your JSON file! Resized x'.$multiplier.'</a>'
                .'</div>'
                .'<div class="col-12 mb-3">'
                .'<img src="'.$this->createUrl.$resizedImageName.'"/>'
                .'</div>';
        }
    }

}
