<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:11
 */
namespace layer\core;

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

    private $controller;
    /**
     * @var array $routes
     */
    private $routes;
    /**
     * @var array $shared
     */
    private $shared;

    private function __construct(){
        $this->request = new Request();
        $this->response = new Response();
        Logger::$request = $this->request;
        Logger::$response = $this->response;
        if(Configuration::get('environment/'.Configuration::$environment.'/buildRoutesMap') || !($this->loadRoutesMap() && $this->loadShared())) {
            $this->discoverRoutes();
            $this->buildSharedMap();
        }
    }

    public static function getInstance() : Router
    {
        if(self::$instance == null) self::$instance = new Router();
        return self::$instance;
    }

    private function loadRoutesMap()
    {
        if(file_exists(PATH."app\core\config\\routes.json"))
        {
            if($data = file_get_contents(PATH."app\core\config\\routes.json"))
            {
                $this->routes = json_decode($data,true);
                return true;
            }
        } else
            return false;
    }

    private function loadShared() {
        if(file_exists(PATH."app\core\config\\shared.json"))
        {
            if($data = file_get_contents(PATH."app\core\config\\shared.json"))
            {
                $this->shared = json_decode($data,true);
                return true;
            }
        } else
            return false;
    }

    private function buildSharedMap() {
        $this->shared = [
            "filters" => [],
            "view" => []
        ];
        $sharedFolder = Configuration::get("locations/shared");

        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sharedFolder));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');

        if(count($phpFiles)) {
            require_once PATH."app/core/mvc/annotation/MVCAnnotations.php";
        }

        $filtersStr = null;
        $filtersFile = [];
        foreach ($phpFiles as $phpFile) {
            if (stripos($phpFile,"\\view\\") > -1) {
                $this->shared['view'][str_replace(".php", "", basename($phpFile))] = trim($phpFile);
            } else if(stripos($phpFile, "\\filters\\") > -1) {
                require_once $phpFile;
                if($filtersStr) {
                    $filtersStr .= '|';
                }
                $filtersStr .= rtrim(basename($phpFile), '.php');
                $filtersFile[rtrim(basename($phpFile), '.php')] = trim($phpFile);
            }
        }

        $filtersNamespace = preg_grep("/($filtersStr)/", get_declared_classes());

        foreach ($filtersNamespace as $fNamespace) {
                $reflectionClass = new \ReflectionAnnotatedClass($fNamespace);
                if($reflectionClass->isSubclassOf(Filter::class)) {
                    $filterAnnotation = $reflectionClass->getAnnotation("Filter");
                    if($filterAnnotation) {
                        if($filterAnnotation->mapped) {
                            if($filterAnnotation->verifyName()) {
                                $filterName = strtolower($filterAnnotation->name);
                            } else {
                                $filterName = strtolower(str_replace("Filter", "", str_replace(".php", "", basename($fNamespace))));
                            }
                            if(!array_key_exists($filterName, $this->shared['filters'])) {
                                $this->shared['filters'][strtolower($filterName)] = [
                                    "namespace" => $reflectionClass->name,
                                    "path" => $filtersFile[basename($fNamespace)]
                                ];
                            }
                        }

                    }
                }
        }

        file_put_contents('app/core/config/shared.json', json_encode($this->shared, JSON_PRETTY_PRINT));
    }

    private function discoverRoutes() {
        $this->routes = [];
        $servicesFolder = Configuration::get("locations/services");

        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($servicesFolder));
        $phpFiles = new \RegexIterator($allFiles, '/\.*controller.*.php$/i');

        if(count($phpFiles)) {
            require_once PATH."app/core/mvc/annotation/MVCAnnotations.php";
        }

        $controllersStr = null;
        $controllersFile = [];
        foreach ($phpFiles as $phpFile) {
            require_once $phpFile;
            if($controllersStr) {
                $controllersStr .= '|';
            }
            $controllersStr .= rtrim(basename($phpFile), '.php');
            $controllersFile[rtrim(basename($phpFile), '.php')] = trim($phpFile);
        }

        $controllersNamespace = preg_grep("/($controllersStr)/", get_declared_classes());

        foreach ($controllersNamespace as $cNamespace) {
            $reflectionController = new \ReflectionAnnotatedClass($cNamespace);
            if($reflectionController->isSubclassOf(Controller::class) && !$reflectionController->getAnnotation('ErrorController')) {

                /**
                 * @var \Controller|\DefaultController $actionAnnotation
                 */
                $controllerAnnotation = $reflectionController->getAnnotation("Controller");
                if(!$controllerAnnotation)
                    $controllerAnnotation = $reflectionController->getAnnotation("DefaultController");

                if($controllerAnnotation && $controllerAnnotation->mapped) {
                    $controllerRouteTemplate = $controllerAnnotation->verifyRouteTemplate() ? trim($controllerAnnotation->verifyRouteTemplate(), '/') : str_replace("controller", "", strtolower(basename($cNamespace)));
                    $controllerLayoutTemplate = Configuration::get('layouts/'.$controllerAnnotation->layoutName, false) ? $controllerAnnotation->layoutName : null;
                    $controllerFilters = array_map('strtolower', $controllerAnnotation->filters);

                    $urlControllerParameters = $controllerAnnotation->grepRouteTemplateParameters();
                    $controllerParameters = [];

                    if(count($urlControllerParameters)) {
                        $reflectionConstructor = $reflectionController->getConstructor();
                        /**
                         * @var \ReflectionParameter $reflectionParameter
                         */
                        foreach ($reflectionConstructor->getParameters() as $reflectionParameter) {
                            $urlPosition = array_search($reflectionParameter->getName(), $urlControllerParameters);
                            $controllerParameters[$reflectionParameter->getPosition()] = [
                                    "name" => $reflectionParameter->getName(),
                                    "required" => !$reflectionParameter->isOptional(),
                                    "default" => $reflectionParameter->isDefaultValueAvailable() ? $reflectionParameter->getDefaultValue() : null,
                                    "allows_null" => $reflectionParameter->allowsNull(),
                                    "type" => $reflectionParameter->hasType() ? $reflectionParameter->getType() . "" : null,
                                    "routeTemplatePosition" => is_int($urlPosition) ? $urlPosition : null
                            ];
                        }
                    }

                    $this->routes[$controllerRouteTemplate] = [
                        "namespace" => $cNamespace,
                        "path" => trim($controllersFile[basename($cNamespace)]),
                        "filters_name" => $controllerFilters,
                        "parameters" => $controllerParameters,
                        "actions" => []
                    ];
                    /**
                     * @var \ReflectionAnnotatedMethod $reflectionMethod
                     */
                    foreach ($reflectionController->getMethods() as $reflectionMethod) {
                        if ($reflectionMethod->isPublic() && $reflectionMethod->hasAnnotation('Action')) {
                            /**
                             * @var \Action $actionAnnotation
                             */
                            $actionAnnotation = $reflectionMethod->getAnnotation('Action');
                            if($actionAnnotation->mapped) {
                                //var_dump($actionAnnotation->grepRouteTemplateParameters());
                                $actionRouteTemplate = $actionAnnotation->verifyRouteTemplate() ? trim($actionAnnotation->verifyRouteTemplate(), '/') : strtolower($reflectionMethod->name);
                                $actionLayoutTemplate = $actionAnnotation->layoutName ?  $actionAnnotation->layoutName : $controllerLayoutTemplate;
                                $actionLayoutTemplate = Configuration::get('layouts/'.$actionLayoutTemplate, false) ? $actionLayoutTemplate : null;
                                $actionFilters = array_diff(array_map("strtolower", $actionAnnotation->filters), $controllerFilters);
                                $actionView = $actionAnnotation->viewName ? $actionAnnotation->viewName : $reflectionMethod->name;
                                // TODO : check if file exists in shared
                                $actionView = file_exists(dirname($controllersFile[basename($cNamespace)])."/view/$actionView.php") ? $actionView : null;

                                $urlActionParameters = $actionAnnotation->grepRouteTemplateParameters();
                                $actionParameters = [];
                                /**
                                 * @var \ReflectionParameter $reflectionParameter
                                 */
                                foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                                    $urlPosition = array_search($reflectionParameter->getName(), $urlActionParameters);
                                    $actionParameters[$reflectionParameter->getPosition()] = [
                                            "name" => $reflectionParameter->getName(),
                                            "required" => !$reflectionParameter->isOptional(),
                                            "default" => $reflectionParameter->isDefaultValueAvailable() ? $reflectionParameter->getDefaultValue() : null,
                                            "allows_null" => $reflectionParameter->allowsNull(),
                                            "type" => $reflectionParameter->hasType() ? $reflectionParameter->getType()."" : null,
                                            "routeTemplatePosition" => is_int($urlPosition) ? $urlPosition : null
                                    ];
                                }

                                $this->routes[$controllerRouteTemplate]['actions'][$actionRouteTemplate] = [
                                    "method_name" => $reflectionMethod->name,
                                    "request_methods" => $actionAnnotation->verifyMethods(),
                                    "filters_name" => $actionFilters,
                                    "view_name" => $actionView,
                                    "layout_name" => $actionLayoutTemplate,
                                    "parameters" => $actionParameters
                                ];
                            }
                        }
                    }

                    if(array_key_exists($controllerAnnotation->defaultAction, $this->routes[$controllerRouteTemplate]['actions'])) {
                        $this->routes[$controllerRouteTemplate]["actions"][""] = ['forward' => $controllerAnnotation->defaultAction];
                    }

                    if($reflectionController->hasAnnotation('DefaultController')) {
                        $this->routes[''] = [
                            "forward" => $controllerRouteTemplate
                        ];
                    }


                }
            } else if($reflectionController->isSubclassOf(ErrorController::class) && $reflectionController->hasAnnotation('ErrorController')) {
                /***
                 * @var $eControllerAnnotation \ErrorController
                 */
                $eControllerAnnotation = $reflectionController->getAnnotation('ErrorController');

                $this->routes['*'] = [
                    "namespace" => $cNamespace,
                    "path" => trim($controllersFile[basename($cNamespace)]),
                    "actions" => []
                ];

                $controllerLayoutTemplate = Configuration::get('layouts/'.$eControllerAnnotation->layoutName, false) ? $eControllerAnnotation->layoutName : null;
                /**
                 * @var \ReflectionAnnotatedMethod $reflectionMethod
                 */
                foreach ($reflectionController->getMethods() as $reflectionMethod) {
                    if ($reflectionMethod->isPublic() && $reflectionMethod->hasAnnotation('ErrorAction')) {
                        /**
                         * @var $eActionAnnotation \ErrorAction
                         */
                        $eActionAnnotation = $reflectionMethod->getAnnotation("ErrorAction");
                        if($eActionAnnotation->mapped) {
                            if(count($eActionAnnotation->errorCodes) > 0) {
                                $actionName = $eActionAnnotation->errorCodes[0];
                                $this->routes['*']['actions'][$actionName] = null;
                                foreach ($eActionAnnotation->errorCodes as $routeName) {
                                    if(!array_key_exists($routeName, $this->routes['*']['actions'])) {
                                        $this->routes['*']['actions'][$routeName] = [
                                            "forward" => $actionName
                                        ];
                                    }
                                }
                            } else {
                                $actionName = $reflectionMethod->name;
                            }
                            if ($reflectionMethod->name == 'index') {
                                $this->routes['*']["actions"][""] = $actionName;
                            }

                            $eActionView = $eActionAnnotation->viewName ? $eActionAnnotation->viewName : $reflectionMethod->name;
                            $eActionView = file_exists(dirname($controllersFile[basename($cNamespace)])."/view/$eActionView.php") ? $eActionView : null;
                            $eLayoutTemplate = $eActionAnnotation->layoutName ? $eActionAnnotation->layoutName : $controllerLayoutTemplate;
                            $eLayoutTemplate = Configuration::get('layouts/'.$eLayoutTemplate, false) ? $eLayoutTemplate : null;

                            $this->routes['*']['actions'][$actionName] = [
                                "method_name" => $reflectionMethod->name,
                                "view_name" => $eActionView,
                                "layout_name" => $eActionView ? $eLayoutTemplate : null
                            ];
                        }
                    }
                }
            }
        }

        file_put_contents('app/core/config/routes.json', json_encode($this->routes, JSON_PRETTY_PRINT));
    }

    public function CORS() {
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
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                // may also be using PUT, PATCH, HEAD etc
                header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");
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
                            throw new Exception("Required parameter is missing", HttpHeaders::BadRequest);
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
                            throw new Exception("Required parameter is missing", HttpHeaders::BadRequest);
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
                throw new Exception("Method not allowed",HttpHeaders::BadRequest);
        } else
            throw new Exception("Requested script not found",HttpHeaders::InternalServerError);
    }

    private function handleError(Exception $e): Response {
        $method = 'index';
        $controllerMetaData = null;
        $actionName = null;
        if(array_key_exists("*", $this->routes) && file_exists($this->routes['*']['path'])) {
            $controllerMetaData = $this->routes['*'];
            require_once $this->routes['*']['path'];
            $namespace = $this->routes['*']['namespace'];
            $reflectionErrorController = new \ReflectionClass($namespace);
            foreach ($controllerMetaData['actions'] as $actionKey => $actionData) {
                if(preg_match('/'.$actionKey.'/', $e->getCode())) {
                    $actionName = $actionKey;
                    $method = $actionData['method_name'];
                }
            }
        } else {
            $reflectionErrorController = new \ReflectionClass(ErrorController::class);
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

        if($controllerMetaData && $controllerMetaData['actions'][$actionName]['view_name']) {
            $layoutName = $controllerMetaData['actions'][$actionName]['layout_name'];
            $viewTemplate = dirname($controllerMetaData['path'])."/view/".$controllerMetaData['actions'][$actionName]['view_name'].".php";
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