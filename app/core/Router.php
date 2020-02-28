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
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\mvc\controller\Controller;
use layer\core\mvc\controller\ErrorController;
use layer\core\mvc\filter\Filter;
use layer\core\mvc\model\ViewModel;
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

    private function __construct(){
        $this->request = new Request();
        $this->response = new Response();
        Logger::$request = $this->request;
        Logger::$response = $this->response;
        if(Configuration::get('layer')[Configuration::$environment]["buildRoutesMap"] || !($this->loadRoutesMap() && $this->loadShared())) {
            $this->buildRoutesMap();
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
        // TODO : implement this
    }

    private function buildRoutesMap()
    {
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

        $file = fopen("./app/core/config/routes_map.json", "w") or die("cannot write in routes_map.json file");
        $json_string = json_encode($this->routes, JSON_PRETTY_PRINT);
        fwrite($file, $json_string);
        fclose($file);

        $file = fopen("./app/core/config/shared.json", "w") or die("cannot write in shared.json file");
        $json_string = json_encode($this->shared, JSON_PRETTY_PRINT);
        fwrite($file, $json_string);
        fclose($file);
    }

    public function handleRequest($baseUrl = null) {
        try {
            $baseUrl = $baseUrl ?? $this->request->getBaseUrl();

            $this->isApiUrlCall = false;

            $this->urlParts = explode('/', trim(strtolower($baseUrl), '/'));

            if (count($this->urlParts) > 0) {
                if (stripos($this->urlParts[0], 'api') > -1) {
                    $this->isApiUrlCall = true;
                    array_shift($this->urlParts);
                }
                if (isset($this->urlParts[0]) && array_key_exists($this->urlParts[0], $this->routes)) {
                    $this->initController($this->routes[$this->urlParts[0]]);
                } else {
                    // route not found
                    throw new Exception("Route not found", HttpHeaders::NotFound);
                }
            } else if (array_key_exists("", $this->routes)) {
                // root controller & actions
                $routeMetaData = $this->routes[""];
                $this->initController($routeMetaData);
            } else {
                throw new Exception("Default route not found", HttpHeaders::NotFound);
            }
        }catch(ForwardException $e) {
            Logger::write("[".$e->getForwardHttpCode()."] Forwarding request to new location: ".$e->getForwardLocation());
            $this->handleRequest($e->getForwardLocation());
        }catch(Exception $e) {
            $this->handleError($e);
        }
    }

    private function handleError(Exception $e) {
        $method = 'index';
        $controllerMetaData = null;
        $actionName = null;
        if(array_key_exists("error", $this->shared) && file_exists($this->shared['error']['path'])) {
            $controllerMetaData = $this->shared['error'];
            require_once $this->shared['error']['path'];
            $namespace = $this->shared['error']['namespace'];
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
            // todo : only key exists
            $viewModel = new ViewModel(dirname($controllerMetaData['path']), $controllerMetaData['actions'][$actionName]['view_name']);
            if ($layoutName = $controllerMetaData['actions'][$actionName]['layout_name']) {
                $arrBefore = Configuration::get("layouts/$layoutName/before");
                $viewModel->addIntroViews($arrBefore);
                $arrAfter = Configuration::get("layouts/$layoutName/after");
                $viewModel->addFinalViews($arrAfter);
            }
            $this->response->setContent($viewModel->generer($this->response->getData()));
        } else {
            $this->response->setContent(json_encode($this->response->getData()));
        }

        $this->sendResponse();

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

    private function initController($routeMetaData) {
        if (array_key_exists('forward', $routeMetaData)) {
            $this->initController($this->routes[$routeMetaData['forward']]);
            return;
        }
        if (file_exists($routeMetaData['path'])) {
            // controller filters enter
            $this->applyFilters($routeMetaData['filters_name']);
            require_once $routeMetaData['path'];
            array_shift($this->urlParts);
            if(isset($this->urlParts[0])) {
                // actions
                if(array_key_exists($this->urlParts[0], $routeMetaData['actions'])) {
                    $this->initAction($routeMetaData, $routeMetaData['actions'][$this->urlParts[0]]);
                } else {
                    // action not found
                    throw new Exception("Action not found",HttpHeaders::NotFound);
                }
            } else if(isset($routeMetaData['actions'][''])) {
                $this->urlParts[0] = '';
                $this->initAction($routeMetaData, $routeMetaData['actions'][$this->urlParts[0]]);
            } else {
                // no default action
                throw new Exception("Action not found",HttpHeaders::NotFound);
            }

        } else {
            // file missing
            throw new Exception("Resource not found",HttpHeaders::InternalServerError);
        }
    }

    private function initAction($routeMetaData, $actionMetaData) {
        if (array_key_exists('forward', $actionMetaData)) {
            $this->initAction($routeMetaData, $routeMetaData['actions'][$actionMetaData['forward']]);
            return;
        }
        if(in_array(strtolower($this->request->getRequestMethod()), $actionMetaData['request_methods'])) {
            $reflectionController = new \ReflectionClass($routeMetaData['namespace']);
            // allowed method
            if($reflectionController->hasMethod($actionMetaData['method_name'])) {
                // action filters enter
                $this->applyFilters($actionMetaData['filters_name']);
                $controller = $reflectionController->newInstance();
                // setting request
                $property = $reflectionController->getProperty('request');
                $property->setAccessible(true);
                $property->setValue($controller, $this->request);
                // setting response
                $property = $reflectionController->getProperty('response');
                $property->setAccessible(true);
                $property->setValue($controller, $this->response);

                $method = $actionMetaData['method_name'];

                array_shift($this->urlParts);
                $actionParameters = [];
                foreach ($actionMetaData['parameters'] as $param) {
                    if(isset($this->urlParts[0])) {
                        $actionParameters[$param['name']] = $this->urlParts[0];
                    } else if( $param['default'] != null || $param['allows_null'] == true) {
                        $actionParameters[$param['name']] = $param['default'];
                    } else {
                        throw new Exception("Required parameter is missing", HttpHeaders::BadRequest);
                    }
                    array_shift($this->urlParts);
                }
                $reflectionMethod = $reflectionController->getMethod($method);
                $reflectionMethod->invokeArgs($controller, $actionParameters);

                if(!$this->isApiUrlCall && $actionMetaData['view_name']) {
                    $viewModel = new ViewModel(dirname($routeMetaData['path']), $actionMetaData['view_name']);
                    if ($layoutName = $actionMetaData['layout_name']) {
                        $arrBefore = Configuration::get("layouts/$layoutName/before");
                        $viewModel->addIntroViews($arrBefore);
                        $arrAfter = Configuration::get("layouts/$layoutName/after");
                        $viewModel->addFinalViews($arrAfter);
                    }
                    $this->response->setContent($viewModel->generer($this->response->getData()));
                } else {
                    $this->response->setContent(json_encode($this->response->getData()));
                }


                // action filters leave
                $this->applyFilters($actionMetaData['filters_name']);
                // controller filters leave
                $this->applyFilters($routeMetaData['filters_name']);

                $this->sendResponse();
            }
        } else {
            // method not allowed
            throw new Exception("Method not allowed",HttpHeaders::BadRequest);
        }
    }

    private function sendResponse() {
        HttpHeaders::ResponseHeader($this->response->getResponseCode());

        foreach ($this->response->getHeaders() as $h => $v) {
            header($h.":".$v, true, $this->response->getResponseCode());
        }

        echo $this->response->getContent();

        $d = new \DateTime();
        $d->setTimestamp((microtime(true) - $this->request->getRequestTime()));

        Logger::write("[".$this->response->getResponseCode()."] Serving content in ".$d->format('s.u')." ms");
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