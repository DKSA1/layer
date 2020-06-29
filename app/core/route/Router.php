<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:11
 */
namespace layer\core\route;

use layer\core\config\Configuration;
use layer\core\error\EConfiguration;
use layer\core\error\EForward;
use layer\core\error\ERedirect;
use layer\core\error\ELayer;
use layer\core\error\EMethod;
use layer\core\error\EParameter;
use layer\core\error\ERoute;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpContentType;
use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\manager\ViewManager;
use layer\core\mvc\controller\Controller;
use layer\core\mvc\controller\ErrorController;
use layer\core\mvc\filter\Filter;
use layer\core\utils\Builder;

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
    /**
     * @var string[]
     */
    private $globalParameters;

    private $apiCall = false;

    public static function getInstance(Request $request) : Router
    {
        if(self::$instance == null) self::$instance = new Router($request);
        return self::$instance;
    }

    private function __construct(Request $request){
        $this->request = $request;
        $this->response = Response::getInstance();
        $this->routes = $this->load('routes_'.Configuration::$environment.'.json');
        $this->shared = $this->load('shared.json');
        if(Configuration::get('environment/'.Configuration::$environment.'/buildRoutesMap') || !($this->routes && $this->shared))
        {
            $this->routes = RouteMapper::buildRoutesMap();
            $this->shared = RouteMapper::buildSharedMap();
            // RouteMapper::buildSiteMap();
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

    public function handleRequest($location = null) : Response {
        try {
            return $this->lookupRoute($location);
        } catch(EForward $e) {
            return $this->handleForward($e);
        } catch(ERedirect $e) {
            return $this->handleRedirect($e);
        } catch(ELayer $e) {
            return $this->handleError($e);
        }
    }

    private function lookupRoute($location = null) : Response {
        $baseUrl = $location ? $location : $this->request->getBaseUrl();
        $baseUrl = trim($baseUrl, "/");
        $baseApi = trim(Configuration::get("environment/".Configuration::$environment."/apiRouteTemplate"),'/');
        $this->apiCall = preg_match('/^'.$baseApi.'/', $baseUrl);
        preg_match('/^'.Configuration::get("environment/".Configuration::$environment."/routeTemplate").'/', $baseUrl, $this->globalParameters);
        if(preg_match('/^'.Configuration::get("environment/".Configuration::$environment."/routeTemplate").'$/', $baseUrl))
        {
            $controller = $this->resolveFinalController($this->routes, "");
            $action = $this->resolveFinalAction($controller, "");
            return $this->initializeControllerAction($controller, $action);
        }
        $controllerRoutes = array_keys($this->routes);
        $filteredController = array_filter($controllerRoutes, function ($controllerTemplate) use ($baseUrl, &$controllerParameters)
        {
            $temp = str_replace("/", "\/", $controllerTemplate);
            if($temp == "" or $temp == "*" or $temp == "**")
                return false;
            return preg_match('/'.$temp.'/', $baseUrl, $controllerParameters[$controllerTemplate]);
        });
        if(count($filteredController) == 0)
        {
            throw new ERoute("Route not found", HttpHeaders::NotFound);
        }
        else
        {
            foreach ($filteredController as $fc) {
                $controllerParameters = count($controllerParameters) >= 1 ? array_slice($controllerParameters[$fc], 1) : [];
                $controller = $this->resolveFinalController($this->routes, $fc);
                if(preg_match('/^'.str_replace("/", "\/", $fc).'$/', $baseUrl)) {
                    $action = $this->resolveFinalAction($controller, "");
                    if(in_array(strtolower($this->request->getRequestMethod()), $action['request_methods']))
                        return $this->initializeControllerAction($controller, $action, [], $controllerParameters);
                }
                $actionRoutes = array_keys($controller['actions']);
                $filteredAction = array_filter($actionRoutes, function ($actionTemplate) use ($fc, $baseUrl, $controller, &$actionParameters) {
                    $actionName = explode(" ",$actionTemplate);
                    $actionName = isset($actionName[1]) ? $actionName[1] : "";
                    // if($actionName == "") return false;
                    $temp = trim($fc.'/'.$actionName,'/');
                    $temp = str_replace("/", "\/", $temp);
                    return preg_match('/'.$temp.'/', $baseUrl, $actionParameters[$actionTemplate]);
                });
                if(count($filteredAction) == 0) {
                    throw new ERoute("Action not found",HttpHeaders::NotFound);
                } else {
                    foreach ($filteredAction as $actionName) {
                        $action = $this->resolveFinalAction($controller, $actionName);
                        if(in_array(strtolower($this->request->getRequestMethod()), $action['request_methods'])) {
                            $actionParameters = count($actionParameters) >= 1 ? array_slice($actionParameters[$actionName],1) : [];
                            return $this->initializeControllerAction($controller, $action, $actionParameters, $controllerParameters);
                        }
                    }
                    throw new EMethod("Method {$this->request->getRequestMethod()} not allowed",HttpHeaders::BadRequest);
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
            throw new ERoute("Controller not found", HttpHeaders::NotFound);
        }

    }

    private function resolveFinalAction($controllerMetaData, $actionName) {
        if(array_key_exists($actionName, $controllerMetaData['actions'])) {
            if (array_key_exists('forward', $controllerMetaData['actions'][$actionName])) {
                return $this->resolveFinalAction($controllerMetaData, $controllerMetaData['actions'][$actionName]['forward']);
            } else
                return $controllerMetaData['actions'][$actionName];
        } else {
            throw new ERoute("Action not found",HttpHeaders::NotFound);
        }
    }

    private function initializeControllerAction($controllerMetaData, $actionMetaData, $actionParams = [], $controllerParams = []) {
        if (file_exists($controllerMetaData['path']))
        {
            // TODO : remove
            // $this->sendCORS($actionMetaData);
            if(!array_key_exists('layout_name', $actionMetaData) && !array_key_exists('view_name', $actionMetaData))
            {
                $this->apiCall = true;
            }
            // check parameters
            $actionParameters = $this->checkParameters($actionParams, $actionMetaData['parameters']);
            $controllerParameters = $this->checkParameters($controllerParams, $controllerMetaData['parameters']);
            // set params in request
            $reflectionRequest = new \ReflectionClass($this->request);
            $propertyRequest = $reflectionRequest->getProperty('routeParameters');
            $propertyRequest->setAccessible(true);
            $propertyRequest->setValue($this->request, ["global" => $this->globalParameters, "controller" => $controllerParameters, "action" => $actionParameters]);
            if(in_array(strtolower($this->request->getRequestMethod()), $actionMetaData['request_methods']))
            {
                $controllerFilters = array_merge(array_keys($this->shared['global']),$controllerMetaData['filters_name']);
                $this->applyFilters($controllerFilters);
                require_once $controllerMetaData['path'];
                $reflectionController = new \ReflectionClass($controllerMetaData['namespace']);
                // setting request & response
                $requestReflection = $reflectionController->getProperty('request');
                $requestReflection->setAccessible(true);
                $requestReflection->setValue($this->request);
                $responseReflection = $reflectionController->getProperty('response');
                $responseReflection->setAccessible(true);
                $responseReflection->setValue($this->response);
                if($reflectionController->hasMethod($actionMetaData['method_name']))
                {
                    $this->applyFilters($actionMetaData['filters_name']);
                    $this->controller = $reflectionController->newInstanceArgs($controllerParameters);
                    // set viewProperty for controller
                    $actionViewProperty = null;
                    if($this->controller instanceof Controller)
                    {
                        $property = $reflectionController->getProperty('viewManager');
                        $property->setAccessible(true);
                        $actionViewProperty = ViewManager::build(dirname($controllerMetaData['path'])."/view/".$actionMetaData['view_name'].".php", $this->shared['view']);
                        $actionViewProperty->setLayoutName($actionMetaData['layout_name']);
                        $actionViewProperty->setContentView($actionMetaData['view_name']);
                        $property->setValue($this->controller, $actionViewProperty);
                    }
                    $reflectionMethod = $reflectionController->getMethod($actionMetaData['method_name']);
                    $result = $reflectionMethod->invokeArgs($this->controller, $actionParameters);
                    if($result != null)
                    {
                        $this->setData($reflectionController, $result);
                        // $reflectionController->setStaticPropertyValue("data", $result);
                    }
                    $this->applyFilters($actionMetaData['filters_name']);
                    $this->applyFilters($controllerFilters);
                    // TODO : replace this with viewproperty
                    if($actionViewProperty)
                    {
                        $reflectionViewProperty = new \ReflectionClass($actionViewProperty);
                        $reflectionMethodViewProperty = $reflectionViewProperty->getMethod('generateView');
                        $reflectionMethodViewProperty->setAccessible(true);
                        $this->response->setMessageBody($reflectionMethodViewProperty->invokeArgs( $actionViewProperty, [$this->getData($reflectionController)] ));

                        // $layoutName = $actionMetaData['layout_name'];
                        // $viewTemplate = dirname($controllerMetaData['path'])."/view/".$actionMetaData['view_name'].".php";
                        // $this->generateLayout($layoutName, $viewTemplate);
                    }
                    else
                    {
                        $this->response->setContentType(IHttpContentType::JSON);
                        $body = $this->getData($reflectionController);
                        if($body)
                            $this->response->setMessageBody( json_encode( $body ));
                    }
                    return $this->response;
                }
            }
            else
                throw new EMethod("Method {$this->request->getRequestMethod()} not allowed",HttpHeaders::BadRequest);
        }
        else
            throw new EConfiguration("Requested script not found",HttpHeaders::InternalServerError);
    }

    private function handleError(ELayer $e): Response {
        $method = 'index';
        $controllerMetaData = null;
        $actionName = null;
        $actionMetaData = null;
        $errorKey = '*';
        if($this->apiCall)
        {
            $errorKey = '**';
        }
        if(array_key_exists($errorKey, $this->routes) && file_exists($this->routes[$errorKey]['path']))
        {
            $controllerMetaData = $this->routes[$errorKey];
            require_once $this->routes[$errorKey]['path'];
            foreach ($controllerMetaData['actions'] as $actionKey => $actionData) {
                    if(preg_match('/'.$actionKey.'/', $e->getCode())) {
                        $actionMetaData = $this->resolveFinalAction($controllerMetaData,$actionKey);
                        $method = $actionMetaData['method_name'];
                    }
            }
            $namespace = $this->routes[$errorKey]['namespace'];
            $reflectionErrorController = new \ReflectionClass($namespace);
        }
        else
        {
            return null;
        }
        // setting request & response
        $requestReflection = $reflectionErrorController->getProperty('request');
        $requestReflection->setAccessible(true);
        $requestReflection->setValue($this->request);
        $responseReflection = $reflectionErrorController->getProperty('response');
        $responseReflection->setAccessible(true);
        $responseReflection->setValue($this->response);

        $controller = $reflectionErrorController->newInstance($e);
        // set viewProperty for controller
        $actionViewProperty = null;
        if($controller instanceof ErrorController)
        {
            $property = $reflectionErrorController->getProperty('viewManager');
            $property->setAccessible(true);
            $actionViewProperty = ViewManager::build(dirname($controllerMetaData['path'])."/view/".$actionMetaData['view_name'].".php", $this->shared['view']);
            $actionViewProperty->setLayoutName($actionMetaData['layout_name']);
            $actionViewProperty->setContentView($actionMetaData['view_name']);
            $property->setValue($controller, $actionViewProperty);
        }
        $this->response->setResponseCode($e->getCode());
        $result = $controller->$method();
        // overwrite response
        if($result != null)
        {
            $this->setData($reflectionErrorController, $result);
            // $reflectionErrorController->setStaticPropertyValue('data', $result);
        }
        if($actionViewProperty)
        {
            $reflectionViewProperty = new \ReflectionClass($actionViewProperty);
            $reflectionMethodViewProperty = $reflectionViewProperty->getMethod('generateView');
            $reflectionMethodViewProperty->setAccessible(true);
            $this->response->setMessageBody($reflectionMethodViewProperty->invokeArgs($actionViewProperty, [$this->getData($reflectionErrorController)] ));

            // $layoutName = $actionMetaData['layout_name'];
            // $viewTemplate = dirname($controllerMetaData['path'])."/view/".$actionMetaData['view_name'].".php";
            // $this->generateLayout($layoutName, $viewTemplate);
        }
        else
        {
            $this->response->setContentType(IHttpContentType::JSON);
            $body = $this->getData($reflectionErrorController);
            if($body)
                $this->response->setMessageBody( json_encode($body) );
        }
        return $this->response;
    }

    private function getData(\ReflectionClass $reflection) {
        $prop = $reflection->getProperty("data");
        $prop->setAccessible(true);
        $val = $prop->getValue();
        $prop->setAccessible(false);
        return $val;
    }

    private function setData(\ReflectionClass $reflection, $data) {
        $prop = $reflection->getProperty("data");
        $prop->setAccessible(true);
        $prop->setValue(null, $data);
        $prop->setAccessible(false);
    }

    private function applyFilters($names) {
        foreach($names as $name) {
            $key = array_key_exists($name, $this->shared['global']) ? "global" : (array_key_exists($name, $this->shared['filters']) ? "filters" : null);
            if($key) {
                if(!array_key_exists($name, $this->filters)) {
                    require_once($this->shared[$key][$name]['path']);
                    $reflectionFilter = new \ReflectionClass($this->shared[$key][$name]['namespace']);
                    $this->filters[$name] = $reflectionFilter->newInstance();
                    $req = $reflectionFilter->getProperty("request");
                    $req->setAccessible(true);
                    $req->setValue($this->filters[$name], $this->request);
                    $req->setAccessible(false);
                    $res = $reflectionFilter->getProperty('response');
                    $res->setAccessible(true);
                    $res->setValue($this->filters[$name], $this->response);
                    $res->setAccessible(false);
                    $result = $this->filters[$name]->beforeAction();
                } else {
                    $result = $this->filters[$name]->afterAction();
                }
                if($result != null)
                {
                    $this->setData(new \ReflectionClass($this->filters[$name]), $result);
                    // $refl->setStaticPropertyValue("data", $result);
                }
            }
        }
    }

    private function handleForward(EForward $e) {
        unset($this->controller);
        $this->filters = [];
        // set forwarded field to true
        $reflectionRequest = new \ReflectionClass($this->request);
        $propertyRequest = $reflectionRequest->getProperty('forwarded');
        $propertyRequest->setAccessible(true);
        $propertyRequest->setValue($this->request, true);
        return $this->handleRequest($e->getInternalRoute());
    }

    private function handleRedirect(ERedirect $e) {
        $this->response->setHeader(IHttpHeaders::Location, $e->getLocation());
        $this->response->setResponseCode($e->getHttpCode());
        return $this->response;
    }

    private function checkParameters($parameters, $parametersInfo) {
        $checkedParameters = [];
        foreach ($parametersInfo as $param)
        {
            if($param['internal'] == false)
            {
                $checkedParameters[$param['name']] = Builder::array2Object($param['namespace'], $this->request->getRequestData(), $param['array']);
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
                    throw new EParameter("Required parameter [{$param['name']}] is missing", HttpHeaders::BadRequest);
                }
            }
        }
        return $checkedParameters;
    }

}