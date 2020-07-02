<?php

namespace layer\core\manager;

use layer\core\error\EParameter;
use layer\core\http\HttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\mvc\controller\CoreController;
use layer\core\utils\Builder;

class ControllerManager
{
    /**
     * @var array
     */
    private $controllers;
    /**
     * @var MapManager
     */
    private static $instance;

    /**
     * @var CoreController[]
     */
    private $instances;

    public static function getInstance(array $modules = null) : ControllerManager
    {
        if(self::$instance == null && $modules) self::$instance = new ControllerManager($modules);
        return self::$instance;
    }

    private function __construct($modules)
    {
        $this->controllers = $modules;
    }

    public function exists(string $controller) {

    }

    public function run(string $controller, string $action, array $params = [], $isError = false) {
        if(array_key_exists($controller, $this->controllers) && array_key_exists($action, $this->controllers[$controller]['actions'])) {
            $metadata = $this->controllers[$controller];
            if(!$isError) {
                $f = FilterManager::getInstance();
                // actions controllers
                foreach ($metadata['filters'] as $filter) {
                    $f->add($filter);
                }
                // actions actions
                foreach ($metadata['actions'][$action]['filters'] as $filter) {
                    $f->add($filter);
                }
                // apply filters
                $f->run(true);
            }
            $controllerParams = array_intersect_key($params, $metadata['parameters']);
            if(!class_exists($controller)) {
                require_once $this->controllers[$controller]['path'];
            }
            if($metadata['actions'][$action]['view'] || $metadata['actions'][$action]['layout']) {
                $v = ViewManager::getInstance();
                $v->setBase($metadata['path']);
                $v->setLayoutName($metadata['actions'][$action]['layout']);
                $v->setContentView($metadata['actions'][$action]['view']);
            }
            $reflectionController = new \ReflectionClass($controller);
            // run controller
            $this->instances[$controller] = $reflectionController->newInstanceArgs($controllerParams);
            if($reflectionController->hasMethod($action)) {
                $reflectionAction = $reflectionController->getMethod($action);
                if($reflectionAction->isPublic()) {
                    $actionParams = array_intersect_key($params, $metadata['actions'][$action]['parameters']);
                    // run action
                    $result = $reflectionAction->invokeArgs($this->instances[$controller], $actionParams);
                }
            }
            if(!$isError) {
                // apply filters
                $f->run(false);
            }
            // get data and pass it to view
            if($v) {
                Response::getInstance()->setMessageBody($v->render($result));
            }
        }
    }


    private function checkParameters($parameters, $parametersMetaData) {
        $checkedParameters = [];
        foreach ($parametersMetaData as $param)
        {
            if($param['internal'] == false)
            {
                $checkedParameters[$param['name']] = Builder::array2Object($param['namespace'], Request::getInstance()->getRequestData(), $param['array']);
            }
            else
            {
                $parameter = array_shift($parameters);
                if(isset($parameter) && $parameter != "")
                {
                    $checkedParameters[$param['name']] = $parameter;
                }
                else if($param['default'] != null || $param['nullable'] == true)
                {
                    $checkedParameters[$param['name']] = $param['default'];
                }
                else
                {
                    throw new EParameter("Required parameter {$param['name']} is missing", HttpHeaders::BadRequest);
                }
            }
        }
        return $checkedParameters;
    }

}