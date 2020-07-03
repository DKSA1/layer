<?php
require_once "./app/Autoloader.php";

$app = \layer\core\App::getInstance("./configuration.json");

if($app->execute()) {
    \layer\core\utils\Logger::write("Serving content successfully");
} else {
    \layer\core\utils\Logger::write("Error occurred");
}
?>