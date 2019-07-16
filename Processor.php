<?php
/**
 * Class Processor
 */
class Processor
{

    protected $tileWidth;
    protected $tileHeight;
    protected $totalRows;
    protected $totalColumns;
    protected $newMapImageWidth;
    protected $newMapImageHeight;
    protected $newMapImage;
    protected $mappedOldToNewTiles = [];
    protected $tilesetsData = [];
    protected $uploadedImages = [];

    /**
     * Processor constructor.
     * @param $json
     * @param $images
     */
    public function __construct($json, $images)
    {
        $this->tileWidth = $json->tilewidth;
        $this->tileHeight = $json->tileheight;
        $this->orderImages($images);
        $this->parseJSON($json);
        var_dump($json);
        var_dump($this->mappedOldToNewTiles);
        var_dump($this->newMapImageWidth, $this->newMapImageHeight);
        var_dump($images);
        $newMapImage = imagecreatetruecolor($this->newMapImageWidth, $this->newMapImageHeight);
        imagealphablending($newMapImage, true);
        imagesavealpha($newMapImage, true);
        $this->newMapImage = $newMapImage;
        $this->createThumbsFromLayersData();
    }

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
        $this->newMapImageWidth = $this->totalColumns * $this->tileWidth;
        $this->totalRows = ceil($totalTiles / $this->totalColumns);
        $this->newMapImageHeight = $this->totalRows * $this->tileHeight;
        // get tilesets data:
        foreach ($json->tilesets as $tileset){
            $tilesetImagePathArray = explode('/', $tileset->image);
            $tilesetImageName = end($tilesetImagePathArray);
            $this->tilesetsData[$tileset->name] = [
                'first' => $tileset->firstgid,
                'last' => ($tileset->firstgid + $tileset->tilecount),
                'tiles_count' => $tileset->tilecount,
                'image' => $tilesetImageName,
                'tmp_image' => $this->getTempImageByName($tilesetImageName)
            ];
        }
    }

    protected function getTempImageByName($tilesetImageName)
    {
        foreach ($this->uploadedImages as $uploadedImage){
            if($uploadedImage['name'] === $tilesetImageName){
                return $uploadedImage['tmp_name'];
            }
        }
        die('ERROR - The specified image in the tileset was not found: '.$tilesetImageName);
    }

    /**
     * @param $baseImage
     * @param $tileIndex
     * @param $tileX
     * @param $tileY
     * @return bool|resource
     */
    protected function createSingleTileImage($baseImage, $tileIndex, $tileX, $tileY)
    {
        $tileImage = false;
        $im = imagecreatefrompng($baseImage);
        imagealphablending($im, true);
        imagesavealpha($im, true);
        $tileData = [
            'x' => $tileX,
            'y' => $tileY,
            'width' => $this->tileWidth,
            'height' => $this->tileHeight
        ];
        $im2 = imagecrop($im, $tileData);
        if ($im2 !== false) {
            $tileImage = imagepng($im2, $tileIndex.'.png');
            imagedestroy($im2);
        }
        return $tileImage;
    }
    
    protected function createThumbsFromLayersData()
    {
        $tilesCounter = 0;
        foreach ($this->mappedOldToNewTiles as $newTileIndex => $mappedTileIndex){
            $tileset = $this->getTilesetByTileIndex($mappedTileIndex);
            $tilePosition = $this->getTilePositionFromTilesetData($tileset, $mappedTileIndex);
            $singleTileImage = $this->createSingleTileImage($tileset['tmp_image'], $newTileIndex, $tilePosition['x'], $tilePosition['y']);
            if($singleTileImage){
                $destX = $tilesCounter;
                $destY = $tilesCounter;
                imagecopy($this->newMapImage, $singleTileImage, $destX, $destY, 0, 0, $this->tileWidth, $this->tileHeight);
            } else {
                die('ERROR - Tile image could not be created.');
            }
        }
        imagepng($this->newMapImage, 'optimizedMap.png');
        imagedestroy($this->newMapImage);
    }

    protected function getTilesetByTileIndex($mappedTileIndex)
    {
        foreach ($this->tilesetsData as $tileset){
            if($mappedTileIndex >= $tileset['first'] && $mappedTileIndex <= $tileset['last']){
                return $tileset;
            }
        }
        die('ERROR - $mappedTileIndex not found');
    }

}
