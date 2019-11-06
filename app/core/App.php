<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 10-11-18
 * Time: 13:51
 */

namespace layer\core;

use layer\core\config\Configuration;
use layer\core\persistence\EntityBuilder;


class App
{
    /**
     * @var App $instance
     */
    private static $instance;
    /**
     * @var Router $router
     */
    private $router;
    /**
     * @var EntityBuilder $builder
     */
    private $builder;


    public static function getInstance() : App
    {
        if(self::$instance == null) self::$instance = new App();
        return self::$instance;
    }

    private function __construct()
    {
        define("PATH",rtrim($_SERVER['SCRIPT_FILENAME'],"index.php"));

        Configuration::load();

        $this->router = Router::getInstance();

        $this->registerGlobals();

        $this->builder = EntityBuilder::getInstance();
        //process
        $this->router->handleRequest();
    }

    private function registerGlobals(){

        $appContext = Configuration::get("globals");
        foreach ($appContext as $c => $v){
            if(is_array($v)) $v = json_encode($v);
            define(strtoupper($c),$v);
        }
        define("APP_ROOT",rtrim($_SERVER["PHP_SELF"],"index.php"));
        define("APP_PUBLIC",APP_ROOT."public");
        define("APP_SERVICE",APP_ROOT."app/service");
        define("APP_CORE",APP_ROOT."app/core");
        define("APP_LIB",APP_ROOT."app/lib");
    }

}