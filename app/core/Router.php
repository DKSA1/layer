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
use layer\core\mvc\controller\Controller;
use layer\core\mvc\filter\Filter;
use layer\service\ErrorsController;
use layer\service;
use layer\core\mvc\MvcAnnotation;

class Router {


    private $request;

    /**
     * @var Router
     */
    private static $instance;

    /**
     * @var Controller[]
     */
    private $controllers;

    public static function getInstance() : Router
    {
        if(self::$instance == null) self::$instance = new Router();
        return self::$instance;
    }

    private function __construct(){
        $this->buildRouteMap();
    }

    private function buildRouteMap()
    {
        $mapping = [
            '#shared' => [
                "filters" => [],
                "view" => []
            ],
            'routes' => []
        ];
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
                    $controllerAnnotation = $reflectionClass->getAnnotation("Controller");
                    //has annotation
                    if($controllerAnnotation){
                        if($controllerAnnotation->mapped) {
                            if($controllerAnnotation->routeName) {
                                $keyName = $controllerAnnotation->routeName;
                            } else {
                                $keyName = str_replace("Controller", "", str_replace(".php", "", basename($phpFile)));
                            }
                            $mapping['routes'][$keyName] = [
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
                                            $actionName = $actionAnnotation->routeName == null ? $reflectionMethod->name : $actionAnnotation->routeName;
                                            $urlPattern = ($controllerAnnotation->api || $actionAnnotation->api ? '/api' : '') .'/'.$keyName.'/'.$actionName.$urlPattern;
                                            $viewName = $actionAnnotation->viewName == null ? $reflectionMethod->name : $actionAnnotation->viewName;
                                            $viewName = file_exists(dirname($phpFile).'/view/'.$viewName.'.php') ? $viewName : null;
                                            $mapping['routes'][$keyName]['actions'][$actionName] = [
                                                "is_api_action" => $controllerAnnotation->api || $actionAnnotation->api,
                                                "method_name" => $reflectionMethod->name,
                                                "url_pattern" => $urlPattern,
                                                "allowed_methods" => $actionAnnotation->methods,
                                                "filters_name" => $actionAnnotation->filters,
                                                "view_name" => $controllerAnnotation->api || $actionAnnotation->api ? null : $viewName,
                                                "use_partial_views" => $controllerAnnotation->api || $actionAnnotation->api ? false : $actionAnnotation->usePartialViews,
                                                "parameters" => $parameters
                                            ];
                                            /// TODO : default method : index
                                        }
                                    }
                                }
                            }

                        }
                    }
                } else if($reflectionClass->isSubclassOf(Filter::class)) {
                    $filterAnnotation = $reflectionClass->getAnnotation("Filter");
                    if($filterAnnotation) {
                        if($filterAnnotation->mapped) {
                            if($filterAnnotation->name) {
                                $keyName = $filterAnnotation->name;
                            } else {
                                $keyName = str_replace("Filter", "", str_replace(".php", "", basename($phpFile)));
                            }
                            $mapping['#shared']['filters'][$keyName] = [
                                "namespace" => $reflectionClass->name,
                                "path" => trim($phpFile),
                            ];
                        }

                    }
                }
            } elseif (stripos($phpFile,"\#shared\\view\\") > -1) {
                $mapping['#shared']['view'][str_replace(".php", "", basename($phpFile))] = trim($phpFile);
            }

        }

        $file = fopen("./app/core/config/routes_mapping.json", "w") or die("cannot write in file");
        $json_string = json_encode($mapping, JSON_PRETTY_PRINT);
        fwrite($file, $json_string);
        fclose($file);
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

            $controleur->setAction($action);
            $controleur->setParams($params);
            $controleur->setData($this->request);
            $controleur->setMethod($_SERVER['REQUEST_METHOD']);
            $controleur->setIsApiCall($apiCall);

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

            $controleur->executeAction();

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

    private function mappingRoutes()
    {
        $directory = PATH.'app/service';
        $scannedDirectory = array_diff(scandir($directory), array('..', '.'));
        foreach ($scannedDirectory as $dir) {
            if(is_dir($directory."/".$dir)) {
                $controllerClassName = ucfirst($dir)."Controller";
                if(file_exists($directory."/".$dir."/".$controllerClassName.".php")) {
                    require_once $directory."/".$dir."/".$controllerClassName.".php";
                    $class = "layer\service\\".$controllerClassName;
                    $reflectionClass = new \ReflectionAnnotatedClass($class);
                    $controllerAnnotation = $reflectionClass->getAnnotation("Controller");

                }
            }
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
        $eController->executeAction();
    }

    /**
     * @return Controller|null
     */
    // return a previously instanciated controller
    public function getInstancedController($controller)
    {
        $reflection = new \ReflectionClass($controller);
        if($reflection && array_key_exists($reflection->getName(),$this->controllers)){
            return $this->controllers[$reflection->getName()];
        }
        return null;
    }

}