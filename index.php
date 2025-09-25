<?php

use App\Core\{Autoloader, Database};


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

<?php
require 'app/core/Autoloader.php';
Autoloader::register();

$db = new Database();

?>
    
</body>
</html>