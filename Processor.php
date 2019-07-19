<?php
/**
 * Class Processor
 */
class Processor
{

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

    /**
     * Processor constructor.
     * @param $json
     * @param $images
     * @param string $newName
     */
    public function __construct($json, $images, $newName = 'optimizedMap', $transparentColor = '#000000')
    {
        $this->newName = $newName;
        $this->transparentColor = $transparentColor;
        $this->tileWidth = $json->tilewidth;
        $this->tileHeight = $json->tileheight;
        $this->orderImages($images);
        $this->parseJSON($json);
        $this->newMapImage = imagecreatetruecolor($this->newMapImageWidth, $this->newMapImageHeight);
        $this->createThumbsFromLayersData();
        $this->createNewJSON($json);
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
        // clean up zeros:
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
        // get tilesets data:
        foreach ($json->tilesets as $tileset){
            $tilesetImagePathArray = explode('/', $tileset->image);
            $tilesetImageName = end($tilesetImagePathArray);
            $this->tileSetData[$tileset->name] = [
                'first' => $tileset->firstgid,
                'last' => ($tileset->firstgid + $tileset->tilecount),
                'tiles_count' => $tileset->tilecount,
                'image' => $tilesetImageName,
                'tmp_image' => $this->getTempImageByName($tilesetImageName),
                'width' => $tileset->imagewidth,
                'height' => $tileset->imageheight
            ];
        }
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
        if(!is_dir('created')){
            mkdir('created', 775);
        }
        imagepng($this->newMapImage, 'created/'.$this->newName.'.png');
        echo '<h2>Download your optimized JSON and image map files!</h2>';
        echo '<img src="created/'.$this->newName.'.png"/>';
        echo '<a href="created/'.$this->newName.'.json">New JSON Map File</a>';
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

    protected function createNewJSON($json)
    {
        foreach ($json->layers as $layer){
            foreach ($layer->data as $k => $data){
                if($data !== 0){
                    $layer->data[$k] = $this->newImagesPositions[$data];
                }
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
        $json->tilesets = [$newTileSet];
        $save = fopen('created/'.$this->newName.'.json', 'w');
        fwrite($save, json_encode($json));
        fclose($save);
    }

}
