<?php

namespace layer\core\manager;

use layer\core\error\EParameter;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpContentType;
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
     * @var CoreController[]
     */
    private $instances;
    /**
     * @var FilterManager
     */
    private $filterManager;

    public function __construct(array $controllers, FilterManager $filterManager)
    {
        $this->controllers = $controllers;
        $this->filterManager = $filterManager;
    }

    public function exists(string $controller) {
        return array_key_exists($controller, $this->controllers);
    }

    public function isApiController(string $controller) {
        if($this->exists($controller)) {
            return $this->controllers[$controller]['api'];
        }
    }

    public function run(Route $activeRoute, Response $response, ViewManager $viewManager = null) {
        if(array_key_exists($activeRoute->getControllerNamespace(), $this->controllers) && array_key_exists($activeRoute->getControllerAction(), $this->controllers[$activeRoute->getControllerNamespace()]['actions'])) {
            $metadata = $this->controllers[$activeRoute->getControllerNamespace()];
            if(!$activeRoute->isError()) {
                // remove all filters
                $this->filterManager->clear();
                // controllers filters
                foreach ($metadata['filters'] as $filter) {
                    $this->filterManager->add($filter);
                }
                // actions filters
                foreach ($metadata['actions'][$activeRoute->getControllerAction()]['filters'] as $filter) {
                    $this->filterManager->add($filter);
                }
                // apply filters in
                $this->filterManager->run(true);
            }
            if(!class_exists($activeRoute->getControllerNamespace())) {
                require_once $this->controllers[$activeRoute->getControllerNamespace()]['path'];
            }
            $reflectionController = new \ReflectionClass($activeRoute->getControllerNamespace());
            // setup viewManager
            if($viewManager) {
                $viewManager->setBase($metadata['path']);
                $viewManager->setLayoutName($metadata['actions'][$activeRoute->getControllerAction()]['layout']);
                $viewManager->setContentView($metadata['actions'][$activeRoute->getControllerAction()]['view']);
                if ($reflectionController->hasProperty("viewManager")) {
                    $vm = $reflectionController->getProperty("viewManager");
                    $vm->setAccessible(true);
                    $vm->setValue(null, $viewManager);
                    $vm->setAccessible(false);
                }
            }
            // new controller
            // var_dump($this->checkParameters($activeRoute->getParams(), $metadata['parameters']));
            // $controllerParams = array_intersect_key($activeRoute->getParams(), $metadata['parameters']);
            $controllerParams = $this->checkParameters($activeRoute->getParams(), $metadata['parameters']);
            $this->instances[$activeRoute->getControllerNamespace()] = $reflectionController->newInstanceArgs($controllerParams);
            // call action
            if($reflectionController->hasMethod($activeRoute->getControllerAction())) {
                $reflectionAction = $reflectionController->getMethod($activeRoute->getControllerAction());
                if($reflectionAction->isPublic()) {
                    // $actionParams = array_intersect_key($activeRoute->getParams(), $metadata['actions'][$activeRoute->getControllerAction()]['parameters']);
                    $actionParams = $this->checkParameters($activeRoute->getParams(), $metadata['actions'][$activeRoute->getControllerAction()]['parameters']);
                    // run action
                    $reflectionAction->invokeArgs($this->instances[$activeRoute->getControllerNamespace()], $actionParams);
                }
            }
            if(!$activeRoute->isError()) {
                // apply filters out
                $this->filterManager->run(false);
            }

            // get data
            $p = $reflectionController->getProperty("data");
            $p->setAccessible(true);
            $data = $p->getValue(null);
            $p->setAccessible(false);

            if($viewManager) {
                $response->setContentType(IHttpContentType::HTML);
                $response->setMessageBody($viewManager->render(!is_scalar($data) ? Builder::object2Array($data) : []));
            } else {
                $response->setContentType(IHttpContentType::JSON);
                $response->setMessageBody($data !== null ? json_encode(Builder::object2Array($data)) : "");
            }
        }
    }

    // TODO : remove request with getInstance
    private function checkParameters($routeParameters, $parametersMetaData) {
        $checkedParameters = [];
        foreach ($parametersMetaData as $name => $param)
        {
            if(isset($routeParameters[$name]))
            {
                if(!$param['internal'] && $param['namespace']) {
                    // user define class
                    $checkedParameters[$name] = Builder::array2Object($param['namespace'], $checkedParameters[$name] = $routeParameters[$name], $param['array']);
                } else {
                    // internal class
                    $checkedParameters[$name] = $routeParameters[$name];
                }
            }
            else if(Request::getInstance()->getRequestData($name))
            {
                if(!$param['internal'] && $param['namespace']) {
                    // user define class
                    $checkedParameters[$name] = Builder::array2Object($param['namespace'], Request::getInstance()->getRequestData($name), $param['array']);
                } else {
                    // internal class
                    $checkedParameters[$name] = Request::getInstance()->getRequestData($name);
                }
            }
            else if(count(Request::getInstance()->getRequestData()) !== 0)
            {
                if(!$param['internal'] && $param['namespace']) {
                    // user define class
                    $checkedParameters[$name] = Builder::array2Object($param['namespace'], Request::getInstance()->getRequestData(), $param['array']);
                } else {
                    // internal class
                    $checkedParameters[$name] = Request::getInstance()->getRequestData();
                }
            }
            else
            {
                if($param['default'] != null || $param['nullable'] == true)
                {
                    $checkedParameters[$name] = $param['default'];
                }
                else
                {
                    throw new EParameter("Required route parameter {$name} is missing", HttpHeaders::BadRequest);
                }
            }
        }
        return $checkedParameters;
    }

}