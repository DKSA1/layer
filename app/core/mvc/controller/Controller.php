<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 15:41
 */
namespace layer\core\mvc\controller;

use layer\core\mvc\view\ViewManager;

abstract class Controller extends CoreController {

    /**
     * @var ViewManager
     */
    protected $viewManager;

    public abstract function index();

}