<!DOCTYPE html>
<html>
    <head>
        <title>DwD - Tilemap Optimizer</title>
        <script src="jquery-3.4.1.min.js" type="text/javascript"></script>
        <script type="text/javascript">
            function addTileMap() {
                let counter = parseInt($('#counter').val());
                counter++;
                $('.tilemaps-container').append(`<br/><label for="tile_map[${counter}]">Tile Map PNG File</label><input id="tile_map[${counter}]" name="tile_map[${counter}]" type="file"/>`);
                $('#counter').val(counter);
            }
        </script>
    </head>
    <body>
        <h1><?php echo 'Tilemap Optimizer'; ?></h1>
        <p>Notes:</p>
        <ul>
            <li>All the tiles in the map images must have the same tile width and height.</li>
            <li>The JSON file must follow the Tiled Maps format.</li>
        </ul>

        <form action="index.php" method="post" enctype="multipart/form-data">
            <p>
                <label for="json_file">Optimized Map New Name</label><input id="new_name" name="new_name" type="text"/>
            </p>
            <p>
                <label for="json_file">Transparent Color (i.e: #ffffff)</label><input id="transparent_color" name="transparent_color" type="text"/>
                <br/><span>If not specified, then black ("#000000"), will be used by default.</span>
            </p>
            <p>
                <label for="json_file">JSON Map File</label><input id="json_file" name="json_file" type="file"/>
                <input type="hidden" id="counter" name="counter" value="0"/>
            </p>
            <div class="tilemaps-container">
                <label for="tile_map[0]">Tile Map PNG File</label><input id="tile_map[0]" name="tile_map[0]" type="file"/>
            </div>
            <p>
                <input value="Add Tilemap PNG File" type="button" id="add_tilemap" onclick="javascript:addTileMap()"/>
            </p>
            <p>
                <input type="submit" value="Process!"/>
            </p>
        </form>
        <?php
        try {
            // check files:
            if(isset($_FILES['json_file']) && isset($_FILES['tile_map'])){
                // get json:
                $jsonContents = json_decode(file_get_contents($_FILES['json_file']['tmp_name']));
                if(!$jsonContents->layers || !$jsonContents->tilesets){
                    die('ERROR CODE - 1 - Invalid JSON file.');
                }
                if(!isset($_FILES['tile_map']['name'])){
                    die('ERROR - Invalid image.');
                }
                // process content:
                require_once('Processor.php');
                $processor = new Processor(
                    $jsonContents,
                    $_FILES['tile_map'],
                    (isset($_POST['new_name']) ? $_POST['new_name'] : false),
                    (isset($_POST['transparent_color']) ? $_POST['transparent_color'] : false)
                );
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        ?>
    </body>
</html>