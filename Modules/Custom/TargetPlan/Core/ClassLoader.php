<?php

require_once ROOT . 'Core/Language.php';
require_once ROOT . 'Core/Router.php';
require_once ROOT . 'Core/Controller.php';
require_once ROOT . 'Core/ImgTFace.php';






$controllers = glob(CONTROLLERS.'{,*/,*/*/,*/*/*/}*Controller.{php}', GLOB_BRACE);
foreach($controllers  as $controller) {
    require_once $controller;
}

$models = glob(MODELS.'{,*/,*/*/,*/*/*/}*.{php}', GLOB_BRACE);
foreach($models  as $model) {
    require_once $model;
}
