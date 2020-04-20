<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 15:41
 */
namespace layer\core\mvc\controller;

use layer\core\mvc\view\ViewProperty;

abstract class Controller extends CoreController {

    /**
     * @var ViewProperty $actionViewProperty
     */
    protected $actionViewProperty;

    public abstract function index();

}