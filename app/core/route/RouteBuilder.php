<?php

namespace layer\core\route;

use layer\core\config\Configuration;
use layer\core\mvc\controller\Controller;
use layer\core\mvc\controller\ErrorController;
use layer\core\mvc\filter\Filter;

class RouteBuilder
{

    public static function buildSharedMap() : array
    {
        $shared = [
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
                $shared['view'][str_replace(".php", "", basename($phpFile))] = trim($phpFile);
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
                        if(!array_key_exists($filterName, $shared['filters'])) {
                            $shared['filters'][strtolower($filterName)] = [
                                "namespace" => $reflectionClass->name,
                                "path" => $filtersFile[basename($fNamespace)]
                            ];
                        }
                    }
                }
            }
        }
        file_put_contents('app/core/config/shared.json', json_encode($shared, JSON_PRETTY_PRINT));
        return $shared;
    }

    public static function buildRoutesMap() : array
    {
        $routes = [];
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
                    $routes[$controllerRouteTemplate] = [
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
                                $routes[$controllerRouteTemplate]['actions'][$actionRouteTemplate] = [
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
                    if(array_key_exists($controllerAnnotation->defaultAction, $routes[$controllerRouteTemplate]['actions'])) {
                        $routes[$controllerRouteTemplate]["actions"][""] = ['forward' => $controllerAnnotation->defaultAction];
                    }
                    if($reflectionController->hasAnnotation('DefaultController')) {
                        $routes[''] = [
                            "forward" => $controllerRouteTemplate
                        ];
                    }
                }
            } else if($reflectionController->isSubclassOf(ErrorController::class) && $reflectionController->hasAnnotation('ErrorController')) {
                /***
                 * @var $eControllerAnnotation \ErrorController
                 */
                $eControllerAnnotation = $reflectionController->getAnnotation('ErrorController');
                $routes['*'] = [
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
                                $routes['*']['actions'][$actionName] = null;
                                foreach ($eActionAnnotation->errorCodes as $routeName) {
                                    if(!array_key_exists($routeName, $routes['*']['actions'])) {
                                        $routes['*']['actions'][$routeName] = [
                                            "forward" => $actionName
                                        ];
                                    }
                                }
                            } else {
                                $actionName = $reflectionMethod->name;
                            }
                            if ($reflectionMethod->name == 'index') {
                                $routes['*']["actions"][""] = $actionName;
                            }
                            $eActionView = $eActionAnnotation->viewName ? $eActionAnnotation->viewName : $reflectionMethod->name;
                            $eActionView = file_exists(dirname($controllersFile[basename($cNamespace)])."/view/$eActionView.php") ? $eActionView : null;
                            $eLayoutTemplate = $eActionAnnotation->layoutName ? $eActionAnnotation->layoutName : $controllerLayoutTemplate;
                            $eLayoutTemplate = Configuration::get('layouts/'.$eLayoutTemplate, false) ? $eLayoutTemplate : null;
                            $routes['*']['actions'][$actionName] = [
                                "method_name" => $reflectionMethod->name,
                                "view_name" => $eActionView,
                                "layout_name" => $eActionView ? $eLayoutTemplate : null
                            ];
                        }
                    }
                }
            }
        }
        file_put_contents('app/core/config/routes.json', json_encode($routes, JSON_PRETTY_PRINT));
        return $routes;
    }
}