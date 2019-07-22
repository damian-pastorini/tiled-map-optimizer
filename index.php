<?php require_once('config.php'); ?>
<!DOCTYPE html>
<html>
<head>
    <title>DwD - Tilemap Optimizer</title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo OPTIMIZER_URL; ?>/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo OPTIMIZER_URL; ?>/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo OPTIMIZER_URL; ?>/favicon-16x16.png">
    <link rel="manifest" href="<?php echo OPTIMIZER_URL; ?>/site.webmanifest">
    <link rel="stylesheet" href="<?php echo OPTIMIZER_URL; ?>/css/bootstrap.min.css"/>
    <style type="text/css">
        body { color:#fff; background-color:#000; }
    </style>
    <script src="<?php echo OPTIMIZER_URL; ?>/js/jquery-3.4.1.min.js" type="text/javascript"></script>
    <script src="<?php echo OPTIMIZER_URL; ?>/js/bootstrap.bundle.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        function addTileMap() {
            let counter = parseInt($('#counter').val());
            counter++;
            $('.tilemaps-container').append(`<br/><input id="tile_map[${counter}]" name="tile_map[${counter}]" type="file"/>`);
            $('#counter').val(counter);
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="row text-center">
            <span class="mb-3">&nbsp;</span>
            <div class="col-12">
                <h1><?php echo 'Tilemap Optimizer'; ?></h1>
            </div>
            <span class="mb-6">&nbsp;</span>
        </div>
        <div class="row text-center">
            <div class="col-12">Notes:</div>
        </div>
        <div class="row text-center">
            <div class="col-12">
                <ul class="text-success">
                    <li>All the tiles in the map images should have the same tile width and height.</li>
                    <li>The JSON file must follow the Tiled maps JSON export format.</li>
                </ul>
            </div>
        </div>
        <form action="<?php echo OPTIMIZER_URL; ?>/" method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-3">
                    <label for="json_file">Optimized Map New Name</label>
                </div>
                <div class="col-9">
                    <input id="new_name" name="new_name" type="text"/>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label for="json_file">Transparent Color (i.e: #ffffff)</label>
                </div>
                <div class="col-9">
                    <input id="transparent_color" name="transparent_color" type="text"/>
                    <span class="text-warning">If not specified, then black ("#000000"), will be used by default.</span>
                </div>
            </div>
            <hr class="mb-2"/>
            <div class="row">
                <div class="col-md-3">
                    <label for="json_file">JSON Map File</label>
                </div>
                <div class="col-9">
                    <input id="json_file" name="json_file" type="file" required="required"/>
                </div>
            </div>
            <hr class="mb-2"/>
            <div class="row">
                <div class="col-md-3">
                    <label for="tile_map[0]">Tile Map PNG File</label>
                    <input type="hidden" id="counter" name="counter" value="0"/>
                    <button class="btn btn-primary" type="button" id="add_tilemap" onclick="javascript:addTileMap()">
                        Add Tilemap PNG File
                    </button>
                </div>
                <div class="col-9">
                    <div class="tilemaps-container">
                        <input id="tile_map[0]" name="tile_map[0]" type="file" required="required"/>
                    </div>
                </div>
            </div>
            <hr class="mb-3"/>
            <div class="row">
                <div class="col-12 text-center">
                    <input class="btn btn-success" type="submit" value="Process!"/>
                </div>
            </div>
            <hr class="mb-6"/>
        </form>
        <div class="row text-center mb-5">
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
                        (isset($_POST['transparent_color']) ? $_POST['transparent_color'] : '#000000')
                    );
                }
            } catch (Exception $e) {
                echo '<div class="text-danger">'.$e->getMessage().'</div>';
            }
            ?>
        </div>
        <div class="row text-center mb-5">
            <div class="col-12 text-center">
                <hr class="mb-1"/>
                <a href="https://www.dwdeveloper.com/">by DwD</a>
            </div>
        </div>
    </div>
</body>
</html>
