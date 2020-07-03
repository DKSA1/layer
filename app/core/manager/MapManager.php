<?php

namespace layer\core\manager;

use layer\core\config\Configuration;
use layer\core\error\EConfiguration;
use layer\core\http\IHttpCodes;
use layer\core\mvc\controller\ApiController;
use layer\core\mvc\controller\ApiErrorController;
use layer\core\mvc\controller\Controller;
use layer\core\mvc\controller\ErrorController;
use layer\core\mvc\filter\Filter;
use layer\core\utils\DocCommentParser;
use layer\core\utils\DocTypeInfo;
use layer\core\utils\File;

class MapManager
{
    private $routes = [];
    private $map = [];
    private $viewsDir;
    private $filtersDir;
    private $modulesDir;

    public function __construct()
    {
        $shared = Configuration::get("locations/shared");
        $modules = Configuration::get("locations/controllers");
        $this->viewsDir = $shared . DIRECTORY_SEPARATOR . "view";
        $this->filtersDir = $shared . DIRECTORY_SEPARATOR . "filters";
        $this->modulesDir = $modules;
    }

    // load existing files
    public function load() {
        // map
        $file = File::getInstance(APP_PATH."layer/map.json");
        if(!$file) throw new EConfiguration("File map.json not found", IHttpCodes::InternalServerError);
        $file->load();
        $this->map = json_decode($file->getContent(), true);
        // routes
        $file = File::getInstance(APP_PATH."layer/routes.json");
        if(!$file) throw new EConfiguration("File routes.json not found", IHttpCodes::InternalServerError);
        $file->load();
        $this->routes = json_decode($file->getContent(), true);
    }

    // build map and routes files
    public function build() {
        $this->routes = [];
        $this->map = [
            "shared" => [
                "globals" => [],
                "filters" => [],
                "views" => []
            ],
            "controllers" => [

            ]
        ];
        // annotations
        require_once APP_PATH."app/core/mvc/annotation/MVCAnnotations.php";
        // filters
        if(file_exists($this->filtersDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->filtersDir));
            $filterFiles = new \RegexIterator($files, '/\.php$/');
            $fileNames = [];
            foreach ($filterFiles as $filter) {
                require_once $filter;
                $fileNames[rtrim(basename($filter), '.php')] = trim($filter->getPathName());
            }
            $namespaces = preg_grep("/(" . implode("|", array_keys($fileNames)) . ")/", get_declared_classes());
            foreach ($namespaces as $namespace) {
                $reflectionClass = new \ReflectionAnnotatedClass($namespace);
                if ($reflectionClass->isSubclassOf(Filter::class)) {
                    $annotation = $reflectionClass->hasAnnotation('Filter') ? $reflectionClass->getAnnotation("Filter") : ($reflectionClass->hasAnnotation('GlobalFilter') ? $reflectionClass->getAnnotation('GlobalFilter') : null);
                    if ($annotation && $annotation->mapped) {
                            if ($annotation->verifyName()) {
                                $filterName = strtolower($annotation->name);
                            } else {
                                $filterName = strtolower(str_ireplace("Filter", "", basename($namespace)));
                            }
                            $this->map['shared']['filters'][$filterName] = [
                                "namespace" => $namespace,
                                "path" => realpath($fileNames[basename($namespace)])
                            ];
                            if($annotation instanceof \GlobalFilter) {
                                array_push($this->map['shared']['globals'], $filterName);
                            }
                    }
                }
            }
        }
        // views
        if(file_exists($this->viewsDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->viewsDir));
            $viewFiles = new \RegexIterator($files, "/\.(php|html)$/");
            foreach ($viewFiles as $view) {
                $this->map["shared"]["views"][preg_replace("/\.(php|html)$/", "", str_replace("\\", "/", trim(str_replace($this->viewsDir, "", $view), DIRECTORY_SEPARATOR)))] = realpath(trim($view->getPathName()));
            }
        }
        // controllers
        if(file_exists($this->modulesDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->modulesDir));
            $modFiles = new \RegexIterator($files, '/\.*controller.*.php$/i');
            $fileNames = [];
            foreach ($modFiles as $mod) {
                require_once $mod;
                $fileNames[rtrim(basename($mod), '.php')] = realpath(trim($mod->getPathName()));
            }
            $namespaces = preg_grep("/(" . implode("|", array_keys($fileNames)) . ")/", get_declared_classes());
            foreach ($namespaces as $namespace) {
                $reflectionClass = new \ReflectionAnnotatedClass($namespace);
                if (($reflectionClass->isSubclassOf(ApiController::class) || $reflectionClass->isSubclassOf(Controller::class)) && $reflectionClass->isInstantiable())
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
                    if($reflectionClass->isSubclassOf(ApiErrorController::class) && $reflectionClass->hasAnnotation('ApiErrorController'))
                    {
                        // api error
                        $isApi = true;
                        $isError = true;
                        $actionAnnotationClass = 'ApiErrorAction';
                        /**
                         * @var \ApiErrorController $controllerAnnotation
                         */
                        $controllerAnnotation = $reflectionClass->getAnnotation('ApiErrorController');
                        $routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/apiRouteTemplate');
                    }
                    else if($reflectionClass->isSubclassOf(ErrorController::class) && $reflectionClass->hasAnnotation('ErrorController'))
                    {
                        // error
                        $isApi = false;
                        $isError = true;
                        $actionAnnotationClass = 'ErrorAction';
                        /**
                         * @var \ErrorController $controllerAnnotation
                         */
                        $controllerAnnotation = $reflectionClass->getAnnotation('ErrorController');
                        $routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/routeTemplate');
                        $layoutName = $controllerAnnotation->layoutName;
                    }
                    else if($reflectionClass->isSubclassOf(ApiController::class) && $reflectionClass->hasAnnotation('ApiController'))
                    {
                        $isApi = true;
                        $actionAnnotationClass = 'ApiAction';
                        /**
                         * @var \ApiController $controllerAnnotation
                         */
                        $controllerAnnotation = $reflectionClass->getAnnotation('ApiController');
                        $defaultActionName = $reflectionClass->hasMethod($controllerAnnotation->defaultAction) ? $controllerAnnotation->defaultAction : null;
                        $routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/apiRouteTemplate')."/".($controllerAnnotation->verifyRouteTemplate() ? $controllerAnnotation->verifyRouteTemplate() : str_ireplace("controller", "", basename($reflectionClass->name)));
                        $filters = $this->checkFilter($controllerAnnotation->getFilters(), $this->map);
                    }
                    else if($reflectionClass->isSubclassOf(Controller::class) && ( $reflectionClass->hasAnnotation('Controller') || $reflectionClass->hasAnnotation('DefaultController')))
                    {
                        $actionAnnotationClass = 'Action';
                        /**
                         * @var \Controller|\DefaultController $controllerAnnotation
                         */
                        $controllerAnnotation = $reflectionClass->hasAnnotation('Controller') ? $reflectionClass->getAnnotation('Controller') : $reflectionClass->getAnnotation('DefaultController');
                        $defaultActionName = $reflectionClass->hasMethod($controllerAnnotation->defaultAction) ? $controllerAnnotation->defaultAction : null;
                        $routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/routeTemplate')."/".($controllerAnnotation->verifyRouteTemplate() ? $controllerAnnotation->verifyRouteTemplate() : str_ireplace("controller", "", basename($reflectionClass->name)));
                        $layoutName = $controllerAnnotation->layoutName;
                        $filters = $this->checkFilter($controllerAnnotation->getFilters(), $this->map);
                        if($controllerAnnotation instanceof \DefaultController) $defaultController = true;
                    }
                    $this->map['controllers'][$namespace] = [
                        "path" => $fileNames[basename($namespace)],
                        "filters" => $filters,
                        "api" => $isApi,
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
                                        if(count($actionAnnotation->errorCodes) === 0) continue;
                                    } else {
                                        /**
                                         * @var \ErrorAction $actionAnnotation
                                         */
                                        $viewName = $this->checkView($m->name, $actionAnnotation->viewName, dirname($fileNames[basename($namespace)]). DIRECTORY_SEPARATOR . "view" . DIRECTORY_SEPARATOR);
                                    }
                                    array_push($methods, '*');
                                    array_push($routeTemplates, ...$actionAnnotation->errorCodes);
                                } else {
                                    if($isApi) {
                                        /**
                                         * @var \ApiAction $actionAnnotation
                                         */
                                    } else {
                                        /**
                                         * @var \Action $actionAnnotation
                                         */
                                        $viewName = $this->checkView($m->name, $actionAnnotation->viewName, dirname($fileNames[basename($namespace)]). DIRECTORY_SEPARATOR . "view" . DIRECTORY_SEPARATOR);
                                    }
                                    $actionFilters = $this->checkFilter($actionAnnotation->getFilters(), $this->map);
                                    array_push($methods, ...$actionAnnotation->verifyMethods());
                                    array_push($routeTemplates, $actionAnnotation->verifyRouteTemplate() ? $actionAnnotation->verifyRouteTemplate() : $m->name);
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
                                            $this->routes[$method][Configuration::get('environment/'.Configuration::$environment."/routeTemplate")] = $handler.$defaultActionName;
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
        $this->save($this->routes, $this->map);
    }

    private function checkFilter($actionFilters, $map) {
        return array_filter($actionFilters, function($filterName) use ($map) {
            if(array_key_exists( strtolower($filterName), $map['shared']['filters'])) return true;
            return false;
        });
    }

    private function checkView($methodName, $actionViewName, $controllerDirectory) {
        $viewName = $actionViewName ? $actionViewName : $methodName;
        if(!file_exists($controllerDirectory.$viewName.".php") && !file_exists($controllerDirectory.$viewName.".html")) {
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

    // save map in files
    private function save(array $routes, array $map) {
        if(!file_exists(APP_PATH."layer")) mkdir(APP_PATH."layer");
        file_put_contents(APP_PATH.'layer/map.json', json_encode($map, JSON_PRETTY_PRINT));
        file_put_contents(APP_PATH.'layer/routes.json', json_encode($routes, JSON_PRETTY_PRINT));
    }

    public function getMap() {
        return $this->map;
    }

    public function getRoutes() {
        return $this->routes;
    }
}