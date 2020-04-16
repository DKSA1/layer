<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:11
 */
namespace layer\core\route;

use layer\core\config\Configuration;
use layer\core\exception\ForwardException;
use layer\core\http\HttpHeaders;
use Exception;
use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\mvc\controller\Controller;
use layer\core\mvc\controller\ErrorController;
use layer\core\mvc\filter\Filter;
use layer\core\mvc\view\Layout;
use layer\core\mvc\view\View;
use layer\core\utils\Logger;

class Router {
    /**
     * @var Request $request
     */
    private $request;

    /**
     * @var Response $response
     */
    private $response;

    /**
     * @var Router
     */
    private static $instance;
    /**
     * @var Filter[] $filters
     */
    private $filters = [];
    private $controller;
    /**
     * @var array $routes
     */
    private $routes;
    /**
     * @var array $shared
     */
    private $shared;

    public static function getInstance() : Router
    {
        if(self::$instance == null) self::$instance = new Router();
        return self::$instance;
    }

    private function __construct(){
        $this->request = new Request();
        $this->response = new Response();
        Logger::$request = $this->request;
        Logger::$response = $this->response;
        $this->routes = $this->load('routes.json');
        $this->shared = $this->load('shared.json');
        if(Configuration::get('environment/'.Configuration::$environment.'/buildRoutesMap') || !($this->routes && $this->shared))
        {
            $this->routes = RouteBuilder::buildRoutesMap();
            $this->shared = RouteBuilder::buildSharedMap();
        }
    }

    private function load($file)
    {
        if(file_exists(PATH."app\core\config\\".$file))
        {
            if($data = file_get_contents(PATH."app\core\config\\".$file))
            {
                return json_decode($data,true);
            }
        } else
            return false;
    }

    public function sendCORS($actionMetaData) {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // origin you want to allow, and if so:
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        } else {
            // No HTTP_ORIGIN set, so we allow any. You can disallow if needed here
            header("Access-Control-Allow-Origin: *");
        }
        // Access-Control headers are received during OPTIONS requests
        if (strtoupper($this->request->getRequestMethod()) == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                header("Access-Control-Allow-Methods: " . implode(", ", $actionMetaData['request_methods']));
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            exit(0);
        }
    }

    public function handleRequest($location = null) : Response {
        try {
            return $this->lookupRoute($location);
        } catch(ForwardException $e) {
            unset($this->controller);
            $this->filters = [];
            $this->response->putHeader(IHttpHeaders::Location, "/".$this->request->getApp()."/".$e->getForwardLocation());
            $this->response->setResponseCode($e->getForwardHttpCode());
            return $this->handleRequest($e->getForwardLocation());
        }catch(Exception $e) {
            return $this->handleError($e);
        }
    }

    private function lookupRoute($location = null) : Response {
        $baseUrl = $location ? $location : $this->request->getBaseUrl();
        $baseUrl = trim($baseUrl, "/");
        if($baseUrl == "") {
            $controller = $this->resolveFinalController($this->routes, "");
            $action = $this->resolveFinalAction($controller, "");
            return $this->initializeControllerAction($controller, $action);
        }
        $controllerRoutes = array_keys($this->routes);
        $controllerParameters = [];
        $filteredController = array_filter($controllerRoutes, function ($controllerTemplate) use ($baseUrl, &$controllerParameters) {
            $temp = str_replace("/", "\/", $controllerTemplate);
            if($controllerTemplate == "" or $controllerTemplate == "*") return false;
            return preg_match('/'.$temp.'/', $baseUrl, $controllerParameters[$controllerTemplate]);
        });
        if(count($filteredController) == 0) {
            throw new Exception("Route not found", HttpHeaders::NotFound);
        } else {
            // TODO : replace by taking only first elem
            foreach ($filteredController as $fc) {
                $controllerParameters = count($controllerParameters) >= 1 ? array_slice($controllerParameters[$fc], 1) : [];
                $controller = $this->resolveFinalController($this->routes, $fc);
                if(preg_match('/^'.str_replace("/", "\/", $fc).'$/', $baseUrl)) {
                    $action = $this->resolveFinalAction($controller, "");
                    return $this->initializeControllerAction($controller, $action, [], $controllerParameters);
                }
                $actionRoutes = array_keys($controller['actions']);
                // TODO : IF no actions throw error
                $actionParameters = [];
                $filteredAction = array_filter($actionRoutes, function ($actionTemplate) use ($fc, $baseUrl, &$actionParameters) {
                    if($actionTemplate == "") return false;
                    $temp = trim($fc.'/'.$actionTemplate,'/');
                    $temp = str_replace("/", "\/", $temp);
                    return preg_match('/'.$temp.'/', $baseUrl, $actionParameters[$actionTemplate]);
                });
                if(count($filteredAction) == 0) {
                    throw new Exception("Action not found",HttpHeaders::NotFound);
                } else {
                    $actionName = array_shift($filteredAction);
                    $action = $this->resolveFinalAction($controller, $actionName);
                    $actionParameters = count($actionParameters) >= 1 ? array_slice($actionParameters[$actionName],1) : [];
                    return $this->initializeControllerAction($controller, $action, $actionParameters, $controllerParameters);
                }
            }
        }
    }

    private function resolveFinalController($routes, $controllerName) {
        if(array_key_exists($controllerName, $routes)) {
            if(array_key_exists('forward', $routes[$controllerName])) {
                return $this->resolveFinalController($routes, $routes[$controllerName]['forward']);
            } else
                return $routes[$controllerName];
        } else {
            throw new Exception("Controller not found", HttpHeaders::NotFound);
        }

    }

    private function resolveFinalAction($controllerMetaData, $actionName) {
        if(array_key_exists($actionName, $controllerMetaData['actions'])) {
            if (array_key_exists('forward', $controllerMetaData['actions'][$actionName])) {
                return $this->resolveFinalAction($controllerMetaData, $controllerMetaData['actions'][$actionName]['forward']);
            } else
                return $controllerMetaData['actions'][$actionName];
        } else {
            throw new Exception("Action not found",HttpHeaders::NotFound);
        }
    }

    private function initializeControllerAction($controllerMetaData, $actionMetaData, $actionParams = [], $controllerParams = []) {
        if (file_exists($controllerMetaData['path'])) {
            $this->sendCORS($actionMetaData);
            if(in_array(strtolower($this->request->getRequestMethod()), $actionMetaData['request_methods'])) {
                $this->applyFilters($controllerMetaData['filters_name']);
                require_once $controllerMetaData['path'];
                $reflectionController = new \ReflectionClass($controllerMetaData['namespace']);
                if($reflectionController->hasMethod($actionMetaData['method_name'])) {
                    $this->applyFilters($actionMetaData['filters_name']);
                    $controllerParameters = [];
                    foreach ($controllerMetaData['parameters'] as $param) {
                        $parameter = array_shift($controllerParams);
                        if(isset($parameter) && $parameter != "") {
                            //$transform = $param['type'] ? $param['type'].'val' : null;
                            //$parameter = function_exists($transform) ? $transform($parameter) : $parameter;
                            $controllerParameters[$param['name']] = $parameter;
                        } else if($param['default'] != null || $param['allows_null'] == true) {
                            $controllerParameters[$param['name']] = $param['default'];
                        } else {
                            throw new Exception("Required controller parameter {$param['name']} is missing", HttpHeaders::BadRequest);
                        }
                    }
                    $this->controller = $reflectionController->newInstanceArgs($controllerParameters);
                    /*
                    //$this->controller = $reflectionController->newInstance();
                    if(array_key_exists("parameters", $controllerMetaData)) {
                        foreach ($controllerMetaData['parameters'] as $param) {
                            $value = array_shift($controllerParams);
                            if($value != '') $this->controller->$param = $value;
                            else $this->controller->$param = null;
                    }
                    }
                    */
                    $property = $reflectionController->getProperty('request');
                    $property->setAccessible(true);
                    $property->setValue($this->controller, $this->request);
                    $property = $reflectionController->getProperty('response');
                    $property->setAccessible(true);
                    $property->setValue($this->controller, $this->response);
                    $method = $actionMetaData['method_name'];
                    $actionParameters = [];
                    foreach ($actionMetaData['parameters'] as $param) {
                        $parameter = array_shift($actionParams);
                        if(isset($parameter) && $parameter != "") {
                            //$transform = $param['type'] ? $param['type'].'val' : null;
                            //$parameter = function_exists($transform) ? $transform($parameter) : $parameter;
                            $actionParameters[$param['name']] = $parameter;
                        } else if($param['default'] != null || $param['allows_null'] == true) {
                            $actionParameters[$param['name']] = $param['default'];
                        } else {
                            throw new Exception("Required action parameter {$param['name']} is missing", HttpHeaders::BadRequest);
                        }
                    }
                    $reflectionMethod = $reflectionController->getMethod($method);
                    $reflectionMethod->invokeArgs($this->controller, $actionParameters);
                    $this->applyFilters($actionMetaData['filters_name']);
                    $this->applyFilters($controllerMetaData['filters_name']);
                    if($actionMetaData['view_name']) {
                        $layoutName = $actionMetaData['layout_name'];
                        $viewTemplate = dirname($controllerMetaData['path'])."/view/".$actionMetaData['view_name'].".php";
                        $this->generateLayout($layoutName, $viewTemplate);
                    } else {
                        $this->response->setContent(json_encode($this->response->getData()));
                    }
                    return $this->response;
                }
            } else
                throw new Exception("Method {$this->request->getRequestMethod()} not allowed",HttpHeaders::BadRequest);
        } else
            throw new Exception("Requested script not found",HttpHeaders::InternalServerError);
    }

    private function handleError(Exception $e): Response {
        $method = 'index';
        $controllerMetaData = null;
        $actionName = null;
        $actionMetaData = null;
        if(array_key_exists("*", $this->routes) && file_exists($this->routes['*']['path'])) {
            $controllerMetaData = $this->routes['*'];
            require_once $this->routes['*']['path'];
            $namespace = $this->routes['*']['namespace'];
            $reflectionErrorController = new \ReflectionClass($namespace);
            foreach ($controllerMetaData['actions'] as $actionKey => $actionData) {
                if(preg_match('/'.$actionKey.'/', $e->getCode())) {
                    $actionMetaData = $this->resolveFinalAction($controllerMetaData,$actionKey);
                    $method = $actionMetaData['method_name'];
                }
            }
        } else {
            return null;
            // $reflectionErrorController = new \ReflectionClass(ErrorController::class);
        }
        $controller = $reflectionErrorController->newInstance();
        // setting request
        $property = $reflectionErrorController->getProperty('request');
        $property->setAccessible(true);
        $property->setValue($controller, $this->request);
        // setting response
        $property = $reflectionErrorController->getProperty('response');
        $property->setAccessible(true);
        $property->setValue($controller, $this->response);
        // setting error
        $property = $reflectionErrorController->getProperty('error');
        $property->setAccessible(true);
        $property->setValue($controller, $e);
        $this->response->setResponseCode($e->getCode());
        $controller->$method();
        if($controllerMetaData && $actionMetaData['view_name']) {
            $layoutName = $actionMetaData['layout_name'];
            $viewTemplate = dirname($controllerMetaData['path'])."/view/".$actionMetaData['view_name'].".php";
            $this->generateLayout($layoutName, $viewTemplate);
        } else {
            $this->response->setContent(json_encode($this->response->getData()));
        }
        return $this->response;
    }

    private function generateLayout($layoutName, $viewTemplate) {
        $layout = new Layout($layoutName);
        foreach (Configuration::get("layouts/$layoutName/before") as $viewName) {
            $view = new View($this->shared['view'][$viewName]);
            $layout->appendView($view);
        }
        $main = new View($viewTemplate);
        $layout->appendView($main);
        foreach (Configuration::get("layouts/$layoutName/after") as $viewName) {
            $view = new View($this->shared['view'][$viewName]);
            $layout->appendView($view);
        }
        $this->response->setContent($layout->render($this->response->getData()));
    }

    private function applyFilters($names) {
        foreach($names as $name) {
            if(array_key_exists($name, $this->shared['filters'])) {
                if(!array_key_exists($name, $this->filters)) {
                    require_once($this->shared['filters'][$name]['path']);
                    $reflectionFilter = new \ReflectionClass($this->shared['filters'][$name]['namespace']);
                    $this->filters[$name] = $reflectionFilter->newInstance();
                    // setting request
                    $property = $reflectionFilter->getProperty('request');
                    $property->setAccessible(true);
                    $property->setValue($this->filters[$name], $this->request);
                    // setting response
                    $property = $reflectionFilter->getProperty('response');
                    $property->setAccessible(true);
                    $property->setValue($this->filters[$name], $this->response);
                    $this->filters[$name]->enter();
                } else {
                    $this->filters[$name]->leave();
                }
            }
        }
    }

}