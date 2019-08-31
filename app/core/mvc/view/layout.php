<?php

/**
 * @var \layer\core\mvc\model\ViewModel $viewModel
 */
$viewModel;

require_once "partials/header.php";
require_once "partials/navbar.php";
require_once "partials/breadcrumbs.php";

require_once $viewModel->generer();

require_once "partials/footer.php";
?>