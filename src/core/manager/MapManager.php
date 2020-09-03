<?php

namespace rloris\layer\core\manager;

use rloris\layer\core\config\Configuration;
use rloris\layer\core\error\EConfiguration;
use rloris\layer\core\http\IHttpCodes;
use rloris\layer\core\mvc\controller\ApiBaseController;
use rloris\layer\core\mvc\controller\ApiErrorBaseController;
use rloris\layer\core\mvc\controller\BaseController;
use rloris\layer\core\mvc\controller\ErrorBaseController;
use rloris\layer\core\mvc\filter\BaseFilter;
use rloris\layer\utils\DocCommentParser;
use rloris\layer\utils\DocTypeInfo;
use rloris\layer\utils\File;

class MapManager
{
    private $routes = [];
    private $map = [];
    private $hash = [];
    private $viewsDir;
    private $filtersDir;
    private $controllersDir;
    private $buildDir;

    public function __construct()
    {
        $shared = Configuration::get("locations/shared");
        $controllers = Configuration::get("locations/controllers");
        $this->buildDir = Configuration::get("locations/build").DIRECTORY_SEPARATOR.APP_ENV;
        $this->viewsDir = $shared . DIRECTORY_SEPARATOR . "views";
        $this->filtersDir = $shared . DIRECTORY_SEPARATOR . "filters";
        $this->controllersDir = $controllers;
    }

    // load existing files
    public function load() {
        // map
        $file = File::getInstance(APP_PATH.$this->buildDir.DIRECTORY_SEPARATOR."map.json");
        if(!$file) throw new EConfiguration("File map.json not found in {$this->buildDir}", IHttpCodes::InternalServerError);
        $file->load();
        $this->map = json_decode($file->getContent(), true);
        // routes
        $file = File::getInstance(APP_PATH.$this->buildDir.DIRECTORY_SEPARATOR."routes.json");
        if(!$file) throw new EConfiguration("File routes.json not found in {$this->buildDir}", IHttpCodes::InternalServerError);
        $file->load();
        $this->routes = json_decode($file->getContent(), true);
    }

    // build map and routes files
    public function build() {
        try
        {
            $hash = FIle::getInstance(APP_PATH.$this->buildDir.DIRECTORY_SEPARATOR."hash.json");
            if(!$hash) throw new EConfiguration("File hash.json not found in {$this->buildDir}", IHttpCodes::InternalServerError);
            $hash->load();
            $this->hash = json_decode($hash->getContent(), true);
            $this->load();
        }
        catch(EConfiguration $e)
        {
            $this->routes = [];
            $this->map = [
                "shared" => [
                    "globals" => [],
                    "filters" => [],
                    "views" => []
                ],
                "controllers" => []
            ];
            $this->hash = [];
        }
        // annotations
        // require_once "src/core/mvc/annotation/RouteAnnotations.php";
        require_once dirname(__DIR__, 1)."/mvc/annotation/RouteAnnotations.php";
        // filters
        if(file_exists($this->filtersDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->filtersDir));
            $filterFiles = new \RegexIterator($files, '/\.*filter.php$/i');
            $fileNames = [];
            foreach ($filterFiles as $filter) {
                if($this->isFileUpdated($filter))
                {
                    require_once $filter;
                    $this->setHashFile(trim($filter));
                    $fileNames[rtrim(basename($filter), '.php')] = trim($filter->getPathName());
                }
            }
            if(count($fileNames) > 0) {
                $namespaces = preg_grep("/(" . implode("|", array_keys($fileNames)) . ")/", get_declared_classes());
                foreach ($namespaces as $namespace) {
                    $reflectionClass = new \ReflectionAnnotatedClass($namespace);
                    if ($reflectionClass->isSubclassOf(BaseFilter::class))
                    {
                        $annotation = $reflectionClass->hasAnnotation('Filter') ? $reflectionClass->getAnnotation("Filter") : ($reflectionClass->hasAnnotation('GlobalFilter') ? $reflectionClass->getAnnotation('GlobalFilter') : null);
                        if ($annotation && $annotation->mapped)
                        {
                            if (($n = $annotation->validateName()))
                            {
                                $filterName = strtolower($n);
                            }
                            else
                            {
                                $filterName = strtolower(str_ireplace("Filter", "", basename($namespace)));
                            }
                            $this->map['shared']['filters'][$filterName] = [
                                "namespace" => $namespace,
                                "path" => $this->fixPathDS($fileNames[basename($namespace)])
                            ];
                            if($annotation instanceof \GlobalFilter)
                            {
                                array_push($this->map['shared']['globals'], $filterName);
                            }
                        }
                    }
                }
            }
        }
        // views
        if(file_exists($this->viewsDir))
        {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->viewsDir));
            $viewFiles = new \RegexIterator($files, "/\.(php|html)$/");
            foreach ($viewFiles as $view)
            {
                $this->map["shared"]["views"][preg_replace("/\.(php|html)$/", "", str_replace("\\", "/", trim(str_replace($this->viewsDir, "", $view), DIRECTORY_SEPARATOR)))] = $this->fixPathDS(trim($view->getPathName()));
            }
        }
        // controllers
        if(file_exists($this->controllersDir))
        {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->controllersDir));
            $modFiles = new \RegexIterator($files, '/\.*controller.php$/i');
            $fileNames = [];
            foreach ($modFiles as $mod)
            {
                if($this->isFileUpdated($mod))
                {
                    require_once $mod;
                    $this->setHashFile(trim($mod));
                    $fileNames[rtrim(basename($mod), '.php')] = $this->fixPathDS(trim($mod->getPathName()));
                }
            }
            if(count($fileNames) > 0) {
                $namespaces = preg_grep("/(" . implode("|", array_keys($fileNames)) . ")/", get_declared_classes());
                foreach ($namespaces as $namespace)
                {
                    $reflectionClass = new \ReflectionAnnotatedClass($namespace);
                    if (($reflectionClass->isSubclassOf(ApiBaseController::class) || $reflectionClass->isSubclassOf(BaseController::class)) && $reflectionClass->isInstantiable())
                    {
                        /**
                         * @var \Controller|\ApiController|\ErrorController|\ApiErrorController|\DefaultController $controllerAnnotation
                         */
                        $controllerAnnotation = null;
                        $actionAnnotationClass = '';
                        $isError = false;
                        $isApi = false;
                        $routeTemplate = '';
                        $handler = $namespace."@";
                        $defaultActionName = null;
                        $defaultController = false;

                        $layoutName = null;
                        $viewName = null;
                        $filters = [];
                        if($reflectionClass->isSubclassOf(ApiErrorBaseController::class) && $reflectionClass->hasAnnotation('ApiErrorController'))
                        {
                            // api error
                            $isApi = true;
                            $isError = true;
                            $actionAnnotationClass = 'ApiErrorAction';
                            /**
                             * @var \ApiErrorController $controllerAnnotation
                             */
                            $controllerAnnotation = $reflectionClass->getAnnotation('ApiErrorController');
                            //$routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/apiRouteTemplate');
                            $routeTemplate = "api";
                        }
                        else if($reflectionClass->isSubclassOf(ErrorBaseController::class) && $reflectionClass->hasAnnotation('ErrorController'))
                        {
                            // error
                            $isApi = false;
                            $isError = true;
                            $actionAnnotationClass = 'ErrorAction';
                            /**
                             * @var \ErrorController $controllerAnnotation
                             */
                            $controllerAnnotation = $reflectionClass->getAnnotation('ErrorController');
                            //$routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/routeTemplate');
                            $routeTemplate = "";
                            $layoutName = $controllerAnnotation->layoutName;
                        }
                        else if($reflectionClass->isSubclassOf(ApiBaseController::class) && $reflectionClass->hasAnnotation('ApiController'))
                        {
                            $isApi = true;
                            $actionAnnotationClass = 'ApiAction';
                            /**
                             * @var \ApiController $controllerAnnotation
                             */
                            $controllerAnnotation = $reflectionClass->getAnnotation('ApiController');
                            $defaultActionName = $reflectionClass->hasMethod($controllerAnnotation->defaultAction) ? $controllerAnnotation->defaultAction : null;
                            $rt = $controllerAnnotation->validateRouteTemplate();
                            $routeTemplate = Configuration::environment('apiRouteTemplate')."/".( $rt ? $rt : str_ireplace("controller", "", basename($reflectionClass->name)));
                            $filters = $this->checkFilter($controllerAnnotation->getFilters(), $this->map);
                        }
                        else if($reflectionClass->isSubclassOf(BaseController::class) && ( $reflectionClass->hasAnnotation('Controller') || $reflectionClass->hasAnnotation('DefaultController')))
                        {
                            $actionAnnotationClass = 'Action';
                            /**
                             * @var \Controller|\DefaultController $controllerAnnotation
                             */
                            $controllerAnnotation = $reflectionClass->hasAnnotation('Controller') ? $reflectionClass->getAnnotation('Controller') : $reflectionClass->getAnnotation('DefaultController');
                            $defaultActionName = $reflectionClass->hasMethod($controllerAnnotation->defaultAction) ? $controllerAnnotation->defaultAction : null;
                            $rt = $controllerAnnotation->validateRouteTemplate();
                            $routeTemplate = Configuration::environment('routeTemplate')."/".( $rt ? $rt : str_ireplace("controller", "", basename($reflectionClass->name)));
                            $layoutName = $controllerAnnotation->layoutName;
                            $filters = $this->checkFilter($controllerAnnotation->getFilters(), $this->map);
                            if($controllerAnnotation instanceof \DefaultController) $defaultController = true;
                        }
                        $this->map['controllers'][$namespace] = [
                            "path" => $fileNames[basename($namespace)],
                            "filters" => $filters,
                            "api" => $isApi,
                            "error" => $isError,
                            "parameters" => $reflectionClass->hasMethod('__construct') ? $this->getParameters($reflectionClass->getConstructor()) : [],
                            "actions" => []
                        ];
                        $routeTemplate = trim($routeTemplate, "/");
                        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                            $controllerMethod = $reflectionClass->getMethod($m->name);
                            if($controllerMethod->hasAnnotation($actionAnnotationClass)) {
                                /**
                                 * @var \Action|\ApiAction|\ErrorAction|\ApiErrorAction $actionAnnotation
                                 */
                                $actionAnnotation = $controllerMethod->getAnnotation($actionAnnotationClass);
                                if($actionAnnotation->mapped) {
                                    $routeTemplates = [];
                                    $methods = [];
                                    $actionFilters = [];
                                    if($isError) {
                                        if($isApi) {
                                            /**
                                             * @var \ApiErrorAction $actionAnnotation
                                             */
                                            // if(count($actionAnnotation->errorCodes) === 0) continue;
                                        } else {
                                            /**
                                             * @var \ErrorAction $actionAnnotation
                                             */
                                            $viewName = $this->checkView($m->name, $actionAnnotation->viewName, dirname($fileNames[basename($namespace)]). DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR);
                                        }
                                        array_push($methods, '*');
                                        if(count($actionAnnotation->errorCodes) !== 0)
                                            array_push($routeTemplates, ...$actionAnnotation->errorCodes);
                                        else
                                            array_push($routeTemplates, '/');
                                    } else {
                                        if($isApi) {
                                            /**
                                             * @var \ApiAction $actionAnnotation
                                             */
                                        } else {
                                            /**
                                             * @var \Action $actionAnnotation
                                             */
                                            $viewName = $this->checkView($m->name, $actionAnnotation->viewName, dirname($fileNames[basename($namespace)]). DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR);
                                        }
                                        $actionFilters = $this->checkFilter($actionAnnotation->getFilters(), $this->map);
                                        array_push($methods, ...$actionAnnotation->validateMethods());
                                        $rt = $actionAnnotation->validateRouteTemplate();
                                        array_push($routeTemplates, $rt ? $rt : $m->name);
                                    }
                                    $this->map['controllers'][$namespace]['actions'][$m->name] = [
                                        "filters" => array_diff($actionFilters, $filters),
                                        "view" => $isApi ? null : $viewName,
                                        "layout" => $isApi ? null : $this->checkLayout($layoutName, $actionAnnotation->layoutName),
                                        "parameters" => $this->getParameters($m)
                                    ];
                                    // default action for controller
                                    if($defaultActionName === $m->name) {
                                        array_push($routeTemplates, "");
                                    }
                                    // add routes
                                    foreach ($methods as $method) {
                                        // add to routes
                                        if(!array_key_exists($method, $this->routes)) {
                                            $this->routes[$method] = [];
                                        }
                                        foreach ($routeTemplates as $template) {
                                            $this->routes[$method][trim($routeTemplate."/".$template, "/")] = $handler.$m->name;
                                            if($defaultController)
                                                $this->routes[$method][Configuration::environment('routeTemplate')] = $handler.$defaultActionName;
                                        }
                                        uksort($this->routes[$method], function($a, $b) {
                                            return strcmp($b, $a) ?: strlen($b) <=> strlen($a);
                                        });
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->save($this->routes, $this->map, $this->hash);
    }

    private function checkFilter($actionFilters, $map) {
        return array_filter($actionFilters, function($filterName) use ($map) {
            if(array_key_exists( strtolower($filterName), $map['shared']['filters'])) return true;
            return false;
        });
    }

    private function checkView($methodName, $actionViewName, $controllerDirectory) {
        $viewName = $actionViewName ? $actionViewName : $methodName;
        if(!file_exists($controllerDirectory.$viewName.".php") && !file_exists($controllerDirectory.$viewName.".html") && file_exists($this->viewsDir."/".ltrim($viewName, "//"))) {
            $viewName = null;
        }
        return $viewName;
    }

    private function checkLayout($controllerLayout, $actionLayout) {
        $layout = $actionLayout ? $actionLayout : $controllerLayout;
        if(Configuration::get('layouts/'.$layout, false))
            return $layout;
        return null;
    }

    private function getParameters(\ReflectionMethod $method)
    {
        $parameters = [];
        $docComments = DocCommentParser::param($method->getDocComment());
        /**
         * @var \ReflectionParameter $reflectionParameter
         */
        foreach ($method->getParameters() as $reflectionParameter)
        {
            $docInfo = isset($docComments[$reflectionParameter->name]) ? DocTypeInfo::getDocType($docComments[$reflectionParameter->name]) : null;
            $parameters[$reflectionParameter->name] = [
                "required" => !$reflectionParameter->isOptional(),
                "default" => $reflectionParameter->isDefaultValueAvailable() ? $reflectionParameter->getDefaultValue() : null,
                "nullable" => $reflectionParameter->allowsNull(),
                "type" => $docInfo ? $docInfo->type : null,
                "array" => $docInfo ? $docInfo->isArray : null,
                "namespace" => $docInfo ? $docInfo->namespace : null,
                "internal" => $docInfo ? $docInfo->isInternal : true
            ];
        }

        return $parameters;
    }

    private function isFileUpdated(string $path) : bool
    {
        if(array_key_exists($path, $this->hash))
        {
            $lastModificationTime = filemtime($path);
            $hash = explode(" ",$this->hash[$path]);
            if(isset($hash[0]) && $lastModificationTime === intval($hash[0]))
            {
                return false;
            }
            if(isset($hash[1]) && md5_file($path) === $hash[1])
            {
                $this->hash[$path] = filemtime($path)." ".$hash[1];
                return false;
            }
        }
        return true;
    }

    private function setHashFile($path)
    {
        $this->hash[$path] = filemtime($path)." ".md5_file($path);
    }

    private function fixPathDS($path)
    {
        return str_replace("\\", DIRECTORY_SEPARATOR, str_replace("/", DIRECTORY_SEPARATOR, $path));
    }

    // save map in files
    private function save(array $routes, array $map, array $hash) {
        if(!file_exists(APP_PATH.$this->buildDir)) mkdir(APP_PATH.$this->buildDir, 0777, true);
        file_put_contents(APP_PATH.$this->buildDir.DIRECTORY_SEPARATOR.'map.json', json_encode($map, JSON_PRETTY_PRINT));
        file_put_contents(APP_PATH.$this->buildDir.DIRECTORY_SEPARATOR.'routes.json', json_encode($routes, JSON_PRETTY_PRINT));
        file_put_contents(APP_PATH.$this->buildDir.DIRECTORY_SEPARATOR.'hash.json', json_encode($hash, JSON_PRETTY_PRINT));
    }

    public function getMap() {
        return $this->map;
    }

    public function getRoutes() {
        return $this->routes;
    }
}