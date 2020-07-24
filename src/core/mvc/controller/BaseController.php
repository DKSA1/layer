<?php
namespace rloris\layer\core\mvc\controller;

use rloris\layer\core\manager\ViewManager;

abstract class BaseController extends CoreController {

    /**
     * @var ViewManager
     */
    protected static $viewManager;
    public abstract function index();
}