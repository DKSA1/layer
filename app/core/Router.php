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

    /**
     * @var Controller[]
     */
    private $controllers;

    /**
    *  @var Filter[]
    */
    private $filters = [];

    private $routes;

    private $shared;

    /**
     * @var string[]
     */
    private $urlParts;

    private $isApiUrlCall;

    private $baseUrl;

    private function __construct(){
        $this->request = new Request();
        $this->response = new Response();
        Logger::$request = $this->request;
        Logger::$response = $this->response;
        if(Configuration::get('environment/'.Configuration::$environment.'/buildRoutesMap') || !($this->loadRoutesMap() && $this->loadShared())) {
            $this->discoverRoutes();
            $this->buildSharedMap();
            // $this->buildRoutesMap();
        }
    }

    public static function getInstance() : Router
    {
        if(self::$instance == null) self::$instance = new Router();
        return self::$instance;
    }

    private function loadRoutesMap()
    {
        if(file_exists(PATH."app\core\config\\routes_map.json"))
        {
            if($data = file_get_contents(PATH."app\core\config\\routes_map.json"))
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

        file_put_contents('app/core/config/shared2.json', json_encode($this->shared, JSON_PRETTY_PRINT));
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

                    $this->routes[$controllerRouteTemplate] = [
                        "namespace" => $cNamespace,
                        "path" => trim($controllersFile[basename($cNamespace)]),
                        "filters_name" => $controllerFilters,
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
                                $actionRouteTemplate = $actionAnnotation->verifyRouteTemplate() ? trim($actionAnnotation->verifyRouteTemplate(), '/') : strtolower($reflectionMethod->name);
                                $actionLayoutTemplate = $actionAnnotation->layoutName ?  $actionAnnotation->layoutName : $controllerLayoutTemplate;
                                $actionLayoutTemplate = Configuration::get('layouts/'.$actionLayoutTemplate, false) ? $actionLayoutTemplate : null;
                                $actionFilters = array_diff(array_map("strtolower", $actionAnnotation->filters), $controllerFilters);
                                $actionView = $actionAnnotation->viewName ? $actionAnnotation->viewName : $reflectionMethod->name;
                                // TODO : check if file exists in shared
                                $actionView = file_exists(dirname($controllersFile[basename($cNamespace)])."/view/$actionView.php") ? $actionView : null;

                                $actionParameters = [];
                                /**
                                 * @var \ReflectionParameter $reflectionParameter
                                 */
                                foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                                    $actionParameters[$reflectionParameter->getPosition()] = [
                                        "name" => $reflectionParameter->getName(),
                                        "required" => !$reflectionParameter->isOptional(),
                                        "default" => $reflectionParameter->isDefaultValueAvailable() ? $reflectionParameter->getDefaultValue() : null,
                                        "allows_null" => $reflectionParameter->allowsNull(),
                                        "type" => $reflectionParameter->hasType() ? $reflectionParameter->getType()."" : null
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

    private function buildRoutesMap()
    {
        $routesTemplate = [];

        $this->shared = [
            "filters" => [],
            "view" => [],
            "error" => null
        ];
        $this->routes = [];
        // controleurs
        $path = dirname(__DIR__) . "\services";

        //check annotations on controller & action
        require_once PATH."app/core/mvc/annotation/MVCAnnotations.php";

        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');
        foreach ($phpFiles as $phpFile) {
            // do not load partial views
            if(!(stripos($phpFile,"\\view\\") > -1)) {
                require_once $phpFile;
                if (stripos($phpFile,"\#shared\\filters\\") > -1) {
                    $reflectionClass = new \ReflectionClass("layer\service\shared\\filters\\".(str_replace(".php", "", basename($phpFile))));
                } else {
                    $reflectionClass = new \ReflectionClass("layer\service\\".(str_replace(".php", "", basename($phpFile))));
                }
                $instance = $reflectionClass->newInstance();
                $reflectionClass = new \ReflectionAnnotatedClass($instance);
                if($reflectionClass->isSubclassOf(Controller::class)) {
                    $default = false;
                    /**
                     * @var \Controller|\DefaultController $actionAnnotation
                     */
                    $controllerAnnotation = $reflectionClass->getAnnotation("Controller");
                    if(!$controllerAnnotation) {
                        $controllerAnnotation = $reflectionClass->getAnnotation("DefaultController");
                        if($controllerAnnotation)
                            $default = true;
                    }
                    //has annotation
                    if($controllerAnnotation){
                        if($controllerAnnotation->mapped) {
                            $controllerRouteNames = $controllerAnnotation->verifyRouteNames();
                            if(count($controllerRouteNames) > 0) {
                                $controllerName = $controllerRouteNames[0];
                                $this->routes[$controllerName] = null;
                                foreach ($controllerRouteNames as $routeName) {
                                    if(!array_key_exists($routeName, $this->routes)) {
                                        $this->routes[$routeName] = [
                                           "forward" => $controllerName
                                        ];
                                    }
                                }
                            } else {
                                $controllerName = strtolower(str_replace("Controller", "", str_replace(".php", "", basename($phpFile))));
                            }
                            $layoutName = (Configuration::get('layouts/'.$controllerAnnotation->layoutName) ? $controllerAnnotation->layoutName : null);
                            $this->routes[$controllerName] = [
                                "namespace" => $reflectionClass->name,
                                "path" => trim($phpFile),
                                "filters_name" => array_map('strtolower', $controllerAnnotation->filters),
                                "actions" => []
                            ];
                            // methods
                            /**
                             * @var \ReflectionAnnotatedMethod $reflectionMethod
                             */
                            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                                if($reflectionMethod->isPublic()) {
                                    /**
                                     * @var \Action $actionAnnotation
                                     */
                                    $actionAnnotation = $reflectionMethod->getAnnotation("Action");
                                    if($actionAnnotation) {
                                        if($actionAnnotation->mapped) {
                                            $parameters = [];
                                            $urlPattern = '';
                                            /**
                                             * @var \ReflectionParameter $reflectionParameter
                                             */
                                            foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                                                $parameters[$reflectionParameter->getPosition()] = [
                                                    "name" => $reflectionParameter->getName(),
                                                    "required" => !$reflectionParameter->isOptional(),
                                                    "default" => $reflectionParameter->isDefaultValueAvailable() ? $reflectionParameter->getDefaultValue() : null,
                                                    "allows_null" => $reflectionParameter->allowsNull(),
                                                    "type" => $reflectionParameter->hasType() ? $reflectionParameter->getType()."" : null
                                                ];
                                                $rgx = '(.+)';
                                                if($reflectionParameter->getType()."" == 'int') $rgx = '(\d+)';
                                                $urlPattern .= '/'.$rgx;
                                            }

                                            $actionRouteNames = $actionAnnotation->verifyRouteNames();
                                            if(count($actionRouteNames) > 0) {
                                                $actionName = $actionRouteNames[0];
                                                $this->routes[$controllerName]['actions'][$actionName] = null;
                                                foreach ($actionRouteNames as $routeName) {
                                                    if(!array_key_exists($routeName, $this->routes[$controllerName]['actions'])) {
                                                        $this->routes[$controllerName]['actions'][$routeName] = [
                                                            "forward" => $actionName
                                                        ];
                                                    }
                                                }
                                            } else {
                                                $actionName = strtolower($reflectionMethod->name);
                                            }
                                            $urlPattern = ($controllerAnnotation->api || $actionAnnotation->api ? '/api' : '') .'/'.$controllerName.'/'.$actionName.$urlPattern;
                                            $viewName = $actionAnnotation->viewName ?? $reflectionMethod->name;
                                            $viewName = file_exists(dirname($phpFile).'/view/'.$viewName.'.php') ? $viewName : null;
                                            $layoutName = !$actionAnnotation->layoutName ? $layoutName : (Configuration::get('layouts/'.$actionAnnotation->layoutName) ? $actionAnnotation->layoutName : null);
                                            $this->routes[$controllerName]['actions'][$actionName] = [
                                                "is_api_action" => $controllerAnnotation->api || $actionAnnotation->api,
                                                "method_name" => $reflectionMethod->name,
                                                "url_pattern" => $urlPattern,
                                                "request_methods" => $actionAnnotation->verifyMethods(),
                                                "filters_name" => array_diff(array_map('strtolower', $actionAnnotation->filters), $this->routes[$controllerName]['filters_name']),
                                                "view_name" => $controllerAnnotation->api || $actionAnnotation->api ? null : $viewName,
                                                "layout_name" => $controllerAnnotation->api || $actionAnnotation->api || !$viewName ? null : $layoutName,
                                                "parameters" => $parameters
                                            ];


                                            // TODO : remove this
                                            $routesTemplate["/".$controllerName.'/'.$actionName."/"] = $this->routes[$controllerName]['namespace']."@".$reflectionMethod->name;
                                        }
                                    }
                                }
                            }

                            if(array_key_exists($controllerAnnotation->defaultAction, $this->routes[$controllerName]['actions'])) {
                                $this->routes[$controllerName]["actions"][""] = ['forward' => $controllerAnnotation->defaultAction];
                            }
                            if($default) {
                                $this->routes[''] = [
                                    "forward" => $controllerName
                                ];
                            }
                        }
                    } else if($reflectionClass->isSubclassOf(ErrorController::class) && $reflectionClass->hasAnnotation('ErrorController')) {

                        $this->shared['error'] = [
                            "namespace" => $reflectionClass->name,
                            "path" => trim($phpFile),
                            "actions" => []
                        ];

                        $controllerAnnotation = $reflectionClass->getAnnotation('ErrorController');
                        $layoutName = (Configuration::get('layouts/'.$controllerAnnotation->layoutName) ? $controllerAnnotation->layoutName : null);

                        /**
                         * @var \ReflectionAnnotatedMethod $reflectionMethod
                         */
                        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                            if($reflectionMethod->isPublic()) {
                                /**
                                 * @var \ErrorAction $actionAnnotation
                                 */
                                $actionAnnotation = $reflectionMethod->getAnnotation("ErrorAction");
                                if ($actionAnnotation) {
                                    if ($actionAnnotation->mapped) {
                                        if(count($actionAnnotation->errorCodes) > 0) {
                                            $actionName = $actionAnnotation->errorCodes[0];
                                            $this->shared['error']['actions'][$actionName] = null;
                                            foreach ($actionAnnotation->errorCodes as $routeName) {
                                                if(!array_key_exists($routeName, $this->shared['error']['actions'])) {
                                                    $this->shared['error']['actions'][$routeName] = [
                                                        "forward" => $actionName
                                                    ];
                                                }
                                            }
                                        } else {
                                            $actionName = $reflectionMethod->name;
                                        }
                                        if ($reflectionMethod->name == 'index') {
                                            $this->shared['error']["actions"][""] = $actionName;
                                        }
                                        $viewName = $actionAnnotation->viewName == null ? $reflectionMethod->name : $actionAnnotation->viewName;
                                        $viewName = file_exists(dirname($phpFile).'/view/'.$viewName.'.php') ? $viewName : null;
                                        $layoutName = !$actionAnnotation->layoutName ? $layoutName : (Configuration::get('layouts/'.$actionAnnotation->layoutName) ? $actionAnnotation->layoutName : null);
                                        $this->shared['error']['actions'][$actionName] = [
                                            "method_name" => $reflectionMethod->name,
                                            "view_name" => $viewName,
                                            "layout_name" => $viewName ? $layoutName : null
                                        ];
                                    }
                                }
                            }
                        }
                    }
                } else if($reflectionClass->isSubclassOf(Filter::class)) {
                    $filterAnnotation = $reflectionClass->getAnnotation("Filter");
                    if($filterAnnotation) {
                        if($filterAnnotation->mapped) {
                            if($filterAnnotation->verifyName()) {
                                $controllerName = $filterAnnotation->name;
                            } else {
                                $controllerName = str_replace("Filter", "", str_replace(".php", "", basename($phpFile)));
                            }
                            $this->shared['filters'][strtolower($controllerName)] = [
                                "namespace" => $reflectionClass->name,
                                "path" => trim($phpFile)
                            ];
                        }

                    }
                }
            } elseif (stripos($phpFile,"\#shared\\view\\") > -1) {
                $this->shared['view'][str_replace(".php", "", basename($phpFile))] = trim($phpFile);
            }

        }

        //file_put_contents("./app/core/config/routes.json", json_encode($routesTemplate, JSON_PRETTY_PRINT));

        $file = fopen("./app/core/config/routes_map.json", "w") or die("cannot write in routes_map.json file");
        $json_string = json_encode($this->routes, JSON_PRETTY_PRINT);
        fwrite($file, $json_string);
        fclose($file);

        $file = fopen("./app/core/config/shared.json", "w") or die("cannot write in shared.json file");
        $json_string = json_encode($this->shared, JSON_PRETTY_PRINT);
        fwrite($file, $json_string);
        fclose($file);
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
        $filteredController = array_filter($controllerRoutes, function ($controllerTemplate) use ($baseUrl) {
            $temp = str_replace("/", "\/", $controllerTemplate);
            if($controllerTemplate == "" or $controllerTemplate == "*") return false;
            return preg_match('/'.$temp.'/', $baseUrl);
        });
        if(count($filteredController) == 0) {
            throw new Exception("Route not found", HttpHeaders::NotFound);
        } else {
            foreach ($filteredController as $fc) {
                $controller = $this->resolveFinalController($this->routes, $fc);
                if(preg_match('/^'.str_replace("/", "\/", $fc).'$/', $baseUrl)) {
                    $action = $this->resolveFinalAction($controller, "");
                    return $this->initializeControllerAction($controller, $action);
                }
                $actionRoutes = array_keys($controller['actions']);
                $groups = [];
                $filteredAction = array_filter($actionRoutes, function ($actionTemplate) use ($fc, $baseUrl, &$groups) {
                    if($actionTemplate == "") return false;
                    $temp = trim($fc.'/'.$actionTemplate,'/');
                    $temp = str_replace("/", "\/", $temp);
                    return preg_match('/'.$temp.'/', $baseUrl, $groups[$actionTemplate]);
                });
                if(count($filteredAction) == 0) {
                    throw new Exception("Action not found",HttpHeaders::NotFound);
                } else {
                    $actionName = array_shift($filteredAction);
                    $action = $this->resolveFinalAction($controller, $actionName);
                    $parameters = count($groups) >= 1 ? array_slice($groups[$actionName],1) : [];
                    return $this->initializeControllerAction($controller, $action, $parameters);
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

    private function initializeControllerAction($controllerMetaData, $actionMetaData, $parameters = []) {
        if (file_exists($controllerMetaData['path'])) {
            if(in_array(strtolower($this->request->getRequestMethod()), $actionMetaData['request_methods'])) {
                $this->applyFilters($controllerMetaData['filters_name']);
                require_once $controllerMetaData['path'];
                $reflectionController = new \ReflectionClass($controllerMetaData['namespace']);
                // allowed method
                if($reflectionController->hasMethod($actionMetaData['method_name'])) {
                    // action filters enter
                    $this->applyFilters($actionMetaData['filters_name']);
                    $controller = $reflectionController->newInstance();
                    // TODO : set controller parameters
                    // setting request
                    $property = $reflectionController->getProperty('request');
                    $property->setAccessible(true);
                    $property->setValue($controller, $this->request);
                    // setting response
                    $property = $reflectionController->getProperty('response');
                    $property->setAccessible(true);
                    $property->setValue($controller, $this->response);

                    $method = $actionMetaData['method_name'];

                    $actionParameters = [];
                    foreach ($actionMetaData['parameters'] as $param) {
                        $parameter = array_shift($parameters);
                        var_dump($param);
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
                    $reflectionMethod->invokeArgs($controller, $actionParameters);

                    // action filters leave
                    $this->applyFilters($actionMetaData['filters_name']);
                    // controller filters leave
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

    private function handleRequest2($baseUrl = null) : Response {
        try {
            $baseUrl = $baseUrl ?? $this->request->getBaseUrl();
            $this->baseUrl = $baseUrl;
            $this->isApiUrlCall = false;

            $this->urlParts = explode('/', trim(strtolower($baseUrl), '/'));

            if (count($this->urlParts) > 0) {
                if (stripos($this->urlParts[0], 'api') > -1) {
                    $this->isApiUrlCall = true;
                    array_shift($this->urlParts);
                }
                if (isset($this->urlParts[0]) && array_key_exists($this->urlParts[0], $this->routes)) {
                    return $this->initController($this->routes[$this->urlParts[0]]);
                } else {
                    // route not found
                    throw new Exception("Route not found", HttpHeaders::NotFound);
                }
            } else if (array_key_exists("", $this->routes)) {
                // root controller & actions
                $routeMetaData = $this->routes[""];
                return $this->initController($routeMetaData);
            } else {
                throw new Exception("Default route not found", HttpHeaders::NotFound);
            }
        }catch(ForwardException $e) {
            $this->response->putHeader(IHttpHeaders::Location, "/".$this->request->getApp()."/".$e->getForwardLocation());
            $this->response->setResponseCode($e->getForwardHttpCode());
            return $this->handleRequest($e->getForwardLocation());
        }catch(Exception $e) {
            return $this->handleError($e);
        }
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

        // TODO : remove this
        error_reporting(E_USER_WARNING);
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

    private function initController($routeMetaData): Response {
        if (array_key_exists('forward', $routeMetaData)) {
            return $this->initController($this->routes[$routeMetaData['forward']]);
        }
        if (file_exists($routeMetaData['path'])) {
            // controller filters enter
            $this->applyFilters($routeMetaData['filters_name']);
            require_once $routeMetaData['path'];
            array_shift($this->urlParts);
            if(isset($this->urlParts[0])) {
                // actions
                if(array_key_exists($this->urlParts[0], $routeMetaData['actions'])) {
                    return $this->initAction($routeMetaData, $routeMetaData['actions'][$this->urlParts[0]]);
                } else {
                    // action not found
                    throw new Exception("Action not found",HttpHeaders::NotFound);
                }
            } else if(isset($routeMetaData['actions'][''])) {
                $this->urlParts[0] = '';
                return $this->initAction($routeMetaData, $routeMetaData['actions'][$this->urlParts[0]]);
            } else {
                // no default action
                throw new Exception("Action not found",HttpHeaders::NotFound);
            }

        } else {
            // file missing
            throw new Exception("Resource not found",HttpHeaders::InternalServerError);
        }
    }

    /**
     * @return Controller|null
     */
    // return a previously instanciated controller for action forward
    public function getInstancedController($controller)
    {
        $reflection = new \ReflectionClass($controller);
        if($reflection && array_key_exists($reflection->getName(),$this->controllers)){
            return $this->controllers[$reflection->getName()];
        }
        return null;
    }

}