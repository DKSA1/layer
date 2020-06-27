<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 10-11-18
 * Time: 13:51
 */

namespace layer\core;

use layer\core\config\Configuration;
use layer\core\http\Request;
use layer\core\route\Router;


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
    /**
     * @var callable $appErrorCallback
     */
    private $appErrorCallback;
    /**
     * @var callable $appFinallyCallback
     */
    private $appFinallyCallback;


    public static function getInstance() : App
    {
        if(self::$instance == null) self::$instance = new App();
        return self::$instance;
    }

    private function __construct()
    {
        define("PATH", rtrim($_SERVER['SCRIPT_FILENAME'],"index.php"));

        Configuration::load();

        $this->registerGlobals();
        //$this->builder = EntityBuilder::getInstance();
    }

    public function handleRequest() {

        $this->startTime = microtime(true);

        $request = Request::getInstance();

        $this->router = Router::getInstance($request);

        $response = $this->router->handleRequest();

        if($response)
        {
            $response->sendResponse();

            if($this->appFinallyCallback)
                call_user_func_array($this->appFinallyCallback, [$response]);
        }
        else
        {
            if($this->appErrorCallback)
                call_user_func_array($this->appErrorCallback, [$request]);
        }
    }

    private function registerGlobals(){

        $appContext = Configuration::get("globals");
        foreach ($appContext as $c => $v) {
            if(is_array($v)) $v = json_encode($v);
            if(!defined(strtoupper($c))) define(strtoupper($c),$v);
        }
        define("APP_ROOT",rtrim($_SERVER["PHP_SELF"],"index.php"));
        // TODO : change with PATH_
        // define("APP_PUBLIC", APP_ROOT."public");
        // define("APP_SERVICES",APP_ROOT."app/services");
        // define("APP_CORE",APP_ROOT."app/core");
        // define("APP_LIB",APP_ROOT."app/lib");
    }

    /**
     * @param $appErrorCallback
     */
    public function setAppErrorCallback($appErrorCallback)
    {
        if(is_callable($appErrorCallback)) {
            $this->appErrorCallback = $appErrorCallback;
        }
    }

    /**
     * @param $appFinallyCallback
     */
    public function setAppFinallyCallback($appFinallyCallback)
    {
        if(is_callable($appFinallyCallback)) {
            $this->appFinallyCallback = $appFinallyCallback;
        }

    }
}