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
    /**
     * @var Response
     */
    private $response;

    public function __construct(array $controllers, FilterManager $filterManager, Response $response)
    {
        $this->controllers = $controllers;
        $this->filterManager = $filterManager;
        $this->response = $response;
    }

    public function controllerExists(string $controller): bool {
        return array_key_exists($controller, $this->controllers);
    }

    public function actionExists(string $controller, string $action): bool {
        return $this->controllerExists($controller) && array_key_exists($action, $this->controllers[$controller]['actions']);
    }

    public function isApiController(string $controller): bool {
        if($this->controllerExists($controller)) {
            return $this->controllers[$controller]['api'];
        }
        return false;
    }

    public function run($controller, $action, $params, ViewManager $viewManager = null) {
        if(array_key_exists($controller, $this->controllers) && array_key_exists($action, $this->controllers[$controller]['actions'])) {
            $metadata = $this->controllers[$controller];
            if(!$metadata["error"]) {
                // remove all filters
                $this->filterManager->clear();
                // controllers filters
                foreach ($metadata['filters'] as $filter) {
                    $this->filterManager->add($filter);
                }
                // actions filters
                foreach ($metadata['actions'][$action]['filters'] as $filter) {
                    $this->filterManager->add($filter);
                }
                // apply filters in
                $this->filterManager->run(true);
            }
            if(!class_exists($controller)) {
                require_once $this->controllers[$controller]['path'];
            }
            $reflectionController = new \ReflectionClass($controller);
            // setup viewManager
            if($viewManager) {
                $viewManager->setBase($metadata['path']);
                $viewManager->setLayoutName($metadata['actions'][$action]['layout']);
                $viewManager->setContentView($metadata['actions'][$action]['view']);
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
            $controllerParams = $this->checkParameters($params, $metadata['parameters']);
            $this->instances[$controller] = $reflectionController->newInstanceArgs($controllerParams);
            // call action
            if($reflectionController->hasMethod($action)) {
                $reflectionAction = $reflectionController->getMethod($action);
                if($reflectionAction->isPublic()) {
                    // $actionParams = array_intersect_key($activeRoute->getParams(), $metadata['actions'][$activeRoute->getControllerAction()]['parameters']);
                    $actionParams = $this->checkParameters($params, $metadata['actions'][$action]['parameters']);
                    // run action
                    $reflectionAction->invokeArgs($this->instances[$controller], $actionParams);
                }
            }
            if(!$metadata["error"]) {
                // apply filters out
                $this->filterManager->run(false);
            }

            // get data
            $p = $reflectionController->getProperty("data");
            $p->setAccessible(true);
            $data = $p->getValue(null);
            $p->setAccessible(false);

            if($viewManager) {
                $this->response->setContentType(IHttpContentType::HTML);
                $this->response->setMessageBody($viewManager->render(!is_scalar($data) ? Builder::object2Array($data) : []));
            } else {
                $this->response->setContentType(IHttpContentType::JSON);
                $this->response->setMessageBody($data !== null ? json_encode(Builder::object2Array($data)) : "");
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