<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:11
 */
namespace layer\core;

use layer\core\config\Configuration;
use layer\core\http\HttpHeaders;
use Exception;
use layer\core\http\IHttpCodes;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\mvc\controller\Controller;
use layer\core\mvc\controller\ErrorController;
use layer\core\mvc\filter\Filter;
use layer\core\mvc\model\ViewModel;
use layer\service\ErrorsController;
use layer\core\mvc\MvcAnnotation;

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

    private $routes;

    private $shared;

    /**
     * @var string[]
     */
    private $urlParts;

    private $isApiUrlCall;

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

    private function __construct(){
        $this->request = new Request();
        $this->response = new Response();

        if(!($this->loadRoutesMap() && $this->loadShared())) {
            $this->buildRouteMap();
        }
    }

    private function buildRouteMap()
    {
        $this->shared = [
            "filters" => [],
            "view" => [],
            "error" => null
        ];
        $this->routes = [];

        // controleurs
        $path = dirname(__DIR__)."\service";

        //check annotations on controller & action
        require_once PATH."app/core/mvc/mvcAnnotations.php";

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
                            $this->routes[$controllerName] = [
                                "namespace" => $reflectionClass->name,
                                "path" => trim($phpFile),
                                "filters_name" => $controllerAnnotation->filters,
                                "actions" => []
                            ];
                            // methods
                            /**
                             * @var \ReflectionAnnotatedMethod $reflectionMethod
                             */
                            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                                if($reflectionMethod->isPublic()) {
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
                                                $urlPattern.='/{'.$reflectionParameter->getName().'}';
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
                                            $viewName = $actionAnnotation->viewName == null ? $reflectionMethod->name : $actionAnnotation->viewName;
                                            $viewName = file_exists(dirname($phpFile).'/view/'.$viewName.'.php') ? $viewName : null;
                                            $this->routes[$controllerName]['actions'][$actionName] = [
                                                "is_api_action" => $controllerAnnotation->api || $actionAnnotation->api,
                                                "method_name" => $reflectionMethod->name,
                                                "url_pattern" => $urlPattern,
                                                "request_methods" => $actionAnnotation->verifyMethods(),
                                                "filters_name" => $actionAnnotation->filters,
                                                "view_name" => $controllerAnnotation->api || $actionAnnotation->api ? null : $viewName,
                                                "use_partial_views" => $controllerAnnotation->api || $actionAnnotation->api ? false : $actionAnnotation->usePartialViews,
                                                "parameters" => $parameters
                                            ];
                                        }
                                    }
                                }
                            }

                            $this->routes[$controllerName]["default_action"] = array_key_exists($controllerAnnotation->defaultAction, $this->routes[$controllerName]['actions']) ? $controllerAnnotation->defaultAction : null;
                            if($default) {
                                $this->routes[''] = [
                                    "forward" => $controllerName
                                ];
                            }
                        }
                    } else if($reflectionClass->isSubclassOf(ErrorController::class) && $reflectionClass->getAnnotation('ErrorController')) {

                        $this->shared['error'] = [
                            "namespace" => $reflectionClass->name,
                            "path" => trim($phpFile),
                            "actions" => []
                        ];

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
                                            $this->shared['error']['default_action'] = $actionName;
                                        }
                                        $viewName = $actionAnnotation->viewName == null ? $reflectionMethod->name : $actionAnnotation->viewName;
                                        $viewName = file_exists(dirname($phpFile).'/view/'.$viewName.'.php') ? $viewName : null;
                                        $this->shared['error']['actions'][$actionName] = [
                                            "method_name" => $reflectionMethod->name,
                                            "view_name" => $viewName,
                                            "use_partial_views" => $viewName ? $actionAnnotation->usePartialViews : false
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
                            $this->shared['filters'][$controllerName] = [
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

    public function handleRequest() {
        try {
            $this->isApiUrlCall = false;

            $this->urlParts = explode('/',trim(strtolower($this->request->getBaseUrl()), '/'));

            if (count($this->urlParts) > 0) {
                if(stripos($this->urlParts[0], 'api') > -1) {
                    $this->isApiUrlCall = true;
                    array_shift($this->urlParts);
                }
                if(isset($this->urlParts[0]) && array_key_exists($this->urlParts[0], $this->routes)) {
                    $this->initController($this->routes[$this->urlParts[0]]);
                } else {
                    // route not found
                    throw new Exception("Route not found",HttpHeaders::NotFound);
                }
            } else if(array_key_exists("", $this->routes)) {
                // root controller & actions
                $routeMetaData = $this->routes[""];
                $this->initController($routeMetaData);
            } else {
                throw new Exception("Default route not found",HttpHeaders::NotFound);
            }
        }catch(Exception $e) {
            // TODO : change RouterException
            $this->handleError($e);
        }
    }

    private function handleError( Exception $e) {
        $method = 'index';
        if(array_key_exists("error", $this->shared) && file_exists($this->shared['error']['path'])) {
            require_once $this->shared['error']['path'];
            $namespace = $this->shared['error']['namespace'];
            $reflectionErrorController = new \ReflectionClass($namespace);
            foreach ($this->shared['error']['actions'] as $actionKey => $actionData) {
                if(preg_match('/'.$actionKey.'/', $e->getCode())) {
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

        if($this->isApiUrlCall) {
            $content = json_encode($this->response->getData());
        } else {
            // todo : only key exists
            $viewModel = new ViewModel(dirname($this->shared['error']['path']), "index");
            if (true) {
                $arrBefore = Configuration::get("defaults/partial/action/before");
                $viewModel->addIntroViews($arrBefore);
                $arrAfter = Configuration::get("defaults/partial/action/after");
                $viewModel->addFinalViews($arrAfter);
            }
            $content = $viewModel->generer($this->response->getData());
        }

        $this->sendResponse($content);

    }

    private function initController($routeMetaData) {
        if (array_key_exists('forward', $routeMetaData)) {
            $routeMetaData = $this->routes[$routeMetaData['forward']];
        }
        if (file_exists($routeMetaData['path'])) {
            require_once $routeMetaData['path'];
            array_shift($this->urlParts);
            if(isset($this->urlParts[0])) {
                // actions
                if(array_key_exists($this->urlParts[0], $routeMetaData['actions'])) {
                    $this->initAction($routeMetaData);
                } else {
                    // action not found
                    throw new Exception("Action not found",HttpHeaders::NotFound);
                }
            } else if($routeMetaData['default_action']) {
                $this->urlParts[0] = $routeMetaData['default_action'];
                $this->initAction($routeMetaData);
            } else {
                // no default action
                throw new Exception("Action not found",HttpHeaders::NotFound);
            }
        } else {
            // file missing
            throw new Exception("Resource not found",HttpHeaders::InternalServerError);
        }
    }

    private function initAction($routeMetaData) {
        $actionMetaData = $routeMetaData['actions'][$this->urlParts[0]];
        if (array_key_exists('forward', $actionMetaData)) {
            $actionMetaData = $routeMetaData['actions'][$actionMetaData['forward']];
        }
        if(in_array(strtolower($this->request->getRequestMethod()), $actionMetaData['request_methods'])) {
            $reflectionController = new \ReflectionClass($routeMetaData['namespace']);
            // allowed method
            if($reflectionController->hasMethod($actionMetaData['method_name'])) {

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

                $controller->$method();

                if($this->isApiUrlCall && $actionMetaData['is_api_action']) {
                    $content = json_encode($this->response->getData());
                } else if(!$this->isApiUrlCall && $actionMetaData['view_name']) {
                    $viewModel = new ViewModel(dirname($routeMetaData['path']), $actionMetaData['view_name']);
                    if ($actionMetaData['use_partial_views']) {
                        $arrBefore = Configuration::get("defaults/partial/action/before");
                        $viewModel->addIntroViews($arrBefore);
                        $arrAfter = Configuration::get("defaults/partial/action/after");
                        $viewModel->addFinalViews($arrAfter);
                    }
                    $content = $viewModel->generer($this->response->getData());
                }

                $this->sendResponse($content);

            }
        } else {
            // method not allowed
            throw new Exception("Method not allowed",HttpHeaders::BadRequest);
        }
    }

    private function sendResponse($content) {

        HttpHeaders::ResponseHeader($this->response->getResponseCode());

        foreach ($this->response->getHeaders() as $h => $v) {
            header($h.":".$v, true, $this->response->getResponseCode());
        }

        echo $content;
    }

    public function routerRequete()
    {
        try {
            //default controller & action
            $def = Configuration::get("defaults/root");
            $controleur = $def['controller'];
            $action = $def['action'];

            $params = null;
            $apiCall = false;

            $this->request = array_merge($_GET, $_POST);

            //specified controller / action
            if (isset($this->request['url']) && $this->request['url'] != "") {
                $array = explode("/",trim($this->request['url'],"/"));
                //is api call
                $apiCall = (isset($array[0]) && strtolower($array[0])=="api") ? true : false;
                if($apiCall) {
                    array_shift($array);
                }
                $controleur = isset($array[0]) ? str_replace(".php","",$array[0]) : $controleur;
                $action = isset($array[1]) ? $array[1] : $action;
                $params = array_slice($array,2);
            }

            $arrRoute = $this->customRoutes($controleur,$action);
            $controleurName = $arrRoute[0];
            $action = $arrRoute[1];

            $controleur = $this->createController($controleurName);

            //$controleur->setAction($action);
            //$controleur->setParams($params);
            //$controleur->setData($this->request);
            //$controleur->setMethod($_SERVER['REQUEST_METHOD']);
            //$controleur->setIsApiCall($apiCall);

            /*if(!$controleur->actionExists($action)){
                throw new Exception("Aucune action ne correspond à votre requète",HttpHeaders::NotFound);
            }*/

            //check annotations on controller & action
            require_once PATH."app/core/mvc/mvcAnnotations.php";
            $reflectionClass = new \ReflectionAnnotatedClass($controleur);
            //check controller
            $controllerAnnotation = $reflectionClass->getAnnotation("Controller");
            //has annotation
            if($controllerAnnotation){
                if(!$controllerAnnotation->mapped)
                {
                    throw new Exception("Controller not found",HttpHeaders::BadRequest);
                }
            }
            //check action
            $reflectionMethod = $reflectionClass->getMethod($action);
            //action exists
            if($reflectionMethod==null)
                throw new Exception("Action not found",HttpHeaders::BadRequest);
            //check method visibility
            if($reflectionMethod->isPrivate() || $reflectionMethod->isProtected())
            {
                throw new Exception("Action not found",HttpHeaders::BadRequest);
            }
            $actionAnnotation = $reflectionMethod->getAnnotation("Action");
            //has annotation
            if($actionAnnotation) {
               //check mapped
               if(!$actionAnnotation->mapped)
               {
                   throw new Exception("Action not found",HttpHeaders::BadRequest);
               }
               //check method allowed
               if(!$actionAnnotation->hasRequestMethod($_SERVER['REQUEST_METHOD']))
               {
                   throw new Exception("Method not allowed",HttpHeaders::BadRequest);
               }
               //api call allowed
               if($apiCall==true && $actionAnnotation->api==false)
               {
                   throw new Exception("Wrong API call",HttpHeaders::BadRequest);
               }
               else if(!$apiCall && $actionAnnotation->api==true)
               {
                   throw new Exception("Wrong API call",HttpHeaders::BadRequest);
               }
            }else if($apiCall==true) {
                //api not available or correctly configured
                throw new Exception("Resource not found",HttpHeaders::BadRequest);
            }

            define("CONTROLLER",$controleurName);
            define("ACTION",$action);

            //$controleur->executeAction();

            }
        catch (Exception $e) {
            $this->gererErreur($e);
        }
    }

    /**
     * @return Controller
     * @throws Exception
     */
    private function createController($controleur) : Controller
    {
        // Contrôleur par défaut => page d'accueil
        //$controleur = $controleur == null ? "homepage" : $controleur;
        // Première lettre en majuscules
        $controleur = ucfirst(strtolower($controleur));
        $classeControleur = $controleur."Controller";
        $fichierControleur = PATH."app\service\\$controleur\\" . $classeControleur . ".php";

        if (file_exists($fichierControleur)){
            // Instanciation du contrôleur adapté à la requête
            require_once $fichierControleur;
            $classeControleur = "layer\service\\".$classeControleur;
            $reflection = new \ReflectionClass($classeControleur);

            if(!$reflection->isSubclassOf(Controller::class))
                throw new Exception("Controller configuration error ", HttpHeaders::InternalServerError);

            /**
             * @var Controller $controleur
             */
            $controleur = $reflection->newInstance();
            // sauvegarde de l'instance (singleton)
            $this->controllers[$classeControleur] = $controleur;
            // retour de l'instance
            return $controleur;
        } else {
            throw new Exception("Resource not found",HttpHeaders::NotFound);
        }

    }

    private function customRoutes($page,$action) : array
    {
        //link request to custom controller
        $addRoutes = Configuration::get("routes/add");
        if(array_key_exists($page,$addRoutes))
        {
            return [$addRoutes[$page]["controller"],isset($addRoutes[$page]["action"]) ? $addRoutes[$page]["action"] : "index"];
        }

        //remove request/controller
        $removeRoutes = Configuration::get("routes/remove");
        if(in_array($page,$removeRoutes))
        {
            throw new Exception("Aucun résultat pour votre recherche",HttpHeaders::NotFound);
        }

        return [$page,$action];
    }

    // gestion et affichage des erreurs
    private function gererErreur(Exception $exception)
    {
        require_once PATH."app\\service\\errors\\errorscontroller.php";
        $eController = new ErrorsController();
        //load default behaviour
        $def = Configuration::get("defaults/root");
        if(!defined('CONTROLLER')) define("CONTROLLER",$def['controller']);
        if(!defined('ACTION')) define("ACTION",$def['action']);
        $eController->code = $exception->getCode();
        $eController->output = ["title"=>"Erreur ".$exception->getCode(),"content" => $exception->getMessage(), "public" => "/git/devboard/public/" ];
        //$eController->executeAction();
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