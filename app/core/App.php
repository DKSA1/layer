<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 10-11-18
 * Time: 13:51
 */

namespace layer\core;

use layer\core\config\Configuration;
use layer\core\route\Router;
use layer\core\utils\Logger;


class App
{
    private $startTime;
    /**
     * @var App $instance
     */
    private static $instance;
    /**
     * @var Router $router
     */
    private $router;


    public static function getInstance() : App
    {
        if(self::$instance == null) self::$instance = new App();
        return self::$instance;
    }

    private function __construct()
    {
        // TRY CATCH APP
        $this->startTime = microtime(true);

        define("PATH",rtrim($_SERVER['SCRIPT_FILENAME'],"index.php"));

        Configuration::load();

        $this->registerGlobals();

        $this->router = Router::getInstance();

        //$this->builder = EntityBuilder::getInstance();

        $response = $this->router->handleRequest();

        $response->sendResponse();

        $d = new \DateTime();
        $d->setTimestamp($response->getResponseTime() - $this->startTime);
        Logger::write("[".$response->getResponseCode()."] Serving content in ".$d->format('s.u')." ms");
    }

    private function registerGlobals(){

        $appContext = Configuration::get("globals");
        foreach ($appContext as $c => $v) {
            if(is_array($v)) $v = json_encode($v);
            define(strtoupper($c),$v);
        }
        define("APP_ROOT",rtrim($_SERVER["PHP_SELF"],"index.php"));
        define("APP_PUBLIC",APP_ROOT."public");
        define("APP_SERVICES",APP_ROOT."app/services");
        define("APP_CORE",APP_ROOT."app/core");
        define("APP_LIB",APP_ROOT."app/lib");
    }

}