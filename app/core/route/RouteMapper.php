<?php

namespace layer\core\route;

use layer\core\config\Configuration;
use layer\core\mvc\controller\ApiController;
use layer\core\mvc\controller\ApiErrorController;
use layer\core\mvc\controller\Controller;
use layer\core\mvc\controller\ErrorController;
use layer\core\mvc\filter\Filter;
use layer\core\utils\DocCommentParser;
use layer\core\utils\DocTypeInfo;

class RouteMapper
{

    public static function buildSharedMap() : array
    {
        $shared = [
            "global" => [],
            "filters" => [],
            "view" => []
        ];
        $sharedFolder = Configuration::get("locations/shared");
        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sharedFolder));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');

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

        if(count($filtersFile)) {
            require_once APP_PATH."app/core/mvc/annotation/MVCAnnotations.php";
        }

        $filtersNamespace = preg_grep("/($filtersStr)/", get_declared_classes());
        foreach ($filtersNamespace as $fNamespace) {
            $reflectionClass = new \ReflectionAnnotatedClass($fNamespace);
            if($reflectionClass->isSubclassOf(Filter::class)) {
                $key = "filters";
                $filterAnnotation = $reflectionClass->getAnnotation("Filter");
                if(!$filterAnnotation)
                {
                    $filterAnnotation = $reflectionClass->getAnnotation('GlobalFilter');
                    $key = "global";
                }
                if($filterAnnotation) {
                    if($filterAnnotation->mapped) {
                        if($filterAnnotation->verifyName()) {
                            $filterName = strtolower($filterAnnotation->name);
                        } else {
                            $filterName = strtolower(str_replace("Filter", "", str_replace(".php", "", basename($fNamespace))));
                        }
                        if(!array_key_exists($filterName, $shared[$key])) {
                            $shared[$key][strtolower($filterName)] = [
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

        $controllersStrRegex = null;
        $controllersFile = [];

        foreach ($phpFiles as $phpFile)
        {
            require_once $phpFile;
            if($controllersStrRegex)
            {
                $controllersStrRegex .= '|';
            }
            $controllersStrRegex .= rtrim(basename($phpFile), '.php');
            $controllersFile[rtrim(basename($phpFile), '.php')] = trim($phpFile);
        }

        if(count($controllersFile))
        {
            require_once APP_PATH."app/core/mvc/annotation/MVCAnnotations.php";
        }

        $controllersNamespace = preg_grep("/($controllersStrRegex)/", get_declared_classes());

        foreach ($controllersNamespace as $cNamespace)
        {
            $reflectionController = new \ReflectionAnnotatedClass($cNamespace);

            if(($reflectionController->isSubclassOf(Controller::class) ||
                $reflectionController->isSubclassOf(ApiController::class))
                && !$reflectionController->getAnnotation('ErrorController')
                && !$reflectionController->getAnnotation('ApiErrorController'))
            {
                /**
                 * @var \Controller|\DefaultController|\ApiController $controllerAnnotation
                 */
                $controllerAnnotation = $reflectionController->getAnnotation("Controller");
                $isApi = false;
                $routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/routeTemplate');
                if(!$controllerAnnotation)
                {
                    $controllerAnnotation = $reflectionController->getAnnotation("DefaultController");
                }
                if(!$controllerAnnotation)
                {
                    $controllerAnnotation = $reflectionController->getAnnotation("ApiController");
                    $isApi = true;
                    $routeTemplate = Configuration::get('environment/'.Configuration::$environment.'/apiRouteTemplate');
                }

                if($controllerAnnotation && $controllerAnnotation->mapped)
                {
                    $controllerRouteTemplate = $controllerAnnotation->verifyRouteTemplate() ? trim($controllerAnnotation->verifyRouteTemplate(), '/') : str_replace("controller", "", strtolower(basename($cNamespace)));
                    $controllerRouteTemplate = trim(trim($routeTemplate,"/")."/".$controllerRouteTemplate,'/');

                    if(!$isApi)
                        $controllerLayoutTemplate = Configuration::get('layouts/'.$controllerAnnotation->layoutName, false) ? $controllerAnnotation->layoutName : null;

                    $controllerFilters = array_map('strtolower', $controllerAnnotation->filters);

                    $routes[$controllerRouteTemplate] = [
                        "namespace" => $cNamespace,
                        "path" => trim($controllersFile[basename($cNamespace)]),
                        "filters_name" => $controllerFilters,
                        "parameters" => $reflectionController->getConstructor() ? self::infoParameters($reflectionController->getConstructor()) : [],
                        "actions" => []
                    ];

                    $defaultAction = isset($controllerAnnotation->defaultAction) ? trim($controllerAnnotation->defaultAction,'/') : null;
                    /**
                     * @var \ReflectionAnnotatedMethod $reflectionMethod
                     */
                    foreach ($reflectionController->getMethods() as $reflectionMethod)
                    {
                        if ($reflectionMethod->isPublic() &&
                            ($reflectionMethod->hasAnnotation('Action') || $reflectionMethod->hasAnnotation('ApiAction')))
                        {
                            /**
                             * @var \Action|\ApiAction $actionAnnotation
                             */
                            if($isApi)
                            {
                                $actionAnnotation = $reflectionMethod->getAnnotation('ApiAction');
                            }
                            else
                            {
                                $actionAnnotation = $reflectionMethod->getAnnotation('Action');
                            }

                            if($actionAnnotation && $actionAnnotation->mapped)
                            {
                                // TODO : check if file exists in shared
                                $actionRouteTemplateName = $actionAnnotation->verifyRouteTemplate() ? trim($actionAnnotation->verifyRouteTemplate(), '/') : strtolower($reflectionMethod->name);
                                // for template and method to make the key unique for this action

                                $actionRouteTemplate = implode("-",$actionAnnotation->verifyMethods())." ".$actionRouteTemplateName;

                                if($defaultAction == $reflectionMethod->name)
                                {  // $actionRouteTemplateName
                                    $defaultAction = $actionRouteTemplate;
                                }

                                if(!$isApi)
                                {
                                    $actionLayoutTemplate = $actionAnnotation->layoutName ?  $actionAnnotation->layoutName : $controllerLayoutTemplate;
                                    $actionLayoutTemplate = Configuration::get('layouts/'.$actionLayoutTemplate, false) ? $actionLayoutTemplate : null;
                                    $actionView = $actionAnnotation->viewName ? $actionAnnotation->viewName : $reflectionMethod->name;
                                    $actionView = file_exists(dirname($controllersFile[basename($cNamespace)])."/view/$actionView.php") ? $actionView : null;
                                }

                                $actionFilters = array_diff(array_map("strtolower", $actionAnnotation->filters), $controllerFilters);
                                // $urlActionParameters = $actionAnnotation->grepRouteTemplateParameters();

                                $routes[$controllerRouteTemplate]['actions'][$actionRouteTemplate] = [
                                    "method_name" => $reflectionMethod->name,
                                    "request_methods" => $actionAnnotation->verifyMethods(),
                                    "filters_name" => $actionFilters,
                                    "parameters" => self::infoParameters($reflectionMethod)
                                ];

                                if(!$isApi)
                                {
                                    $routes[$controllerRouteTemplate]['actions'][$actionRouteTemplate]['view_name'] = $actionView;
                                    $routes[$controllerRouteTemplate]['actions'][$actionRouteTemplate]['layout_name'] = $actionLayoutTemplate;
                                }

                            }
                        }
                    }
                    if(array_key_exists($defaultAction, $routes[$controllerRouteTemplate]['actions']))
                    {
                        $routes[$controllerRouteTemplate]["actions"][""] = ['forward' => $defaultAction];
                    }
                    if($reflectionController->hasAnnotation('DefaultController'))
                    {
                        $routes[''] = [
                            "forward" => $controllerRouteTemplate
                        ];
                    }

                }
            } else if(($reflectionController->isSubclassOf(ErrorController::class) && $reflectionController->hasAnnotation('ErrorController'))
                || ($reflectionController->isSubclassOf(ApiErrorController::class) && $reflectionController->hasAnnotation('ApiErrorController')))
            {
                /***
                 * @var $eControllerAnnotation \ErrorController
                 */
                $eControllerAnnotation = $reflectionController->getAnnotation('ErrorController');
                $isApiError = false;
                $errorKey = '*';
                if(!$eControllerAnnotation)
                {
                    $eControllerAnnotation = $reflectionController->getAnnotation('ApiErrorController');
                    $isApiError = true;
                    $errorKey = '**';
                }

                $routes[$errorKey] = [
                    "namespace" => $cNamespace,
                    "path" => trim($controllersFile[basename($cNamespace)]),
                    "actions" => []
                ];

                if(!$isApiError) {
                    $controllerLayoutTemplate = Configuration::get('layouts/'.$eControllerAnnotation->layoutName, false) ? $eControllerAnnotation->layoutName : null;
                }
                /**
                 * @var \ReflectionAnnotatedMethod $reflectionMethod
                 */
                foreach ($reflectionController->getMethods() as $reflectionMethod)
                {
                    if ($reflectionMethod->isPublic() && $reflectionMethod->hasAnnotation('ErrorAction')
                        || $reflectionMethod->hasAnnotation('ApiErrorAction'))
                    {
                        /**
                         * @var $eActionAnnotation \ErrorAction
                         */
                        $eActionAnnotation = $reflectionMethod->getAnnotation("ErrorAction");
                        if(!$eActionAnnotation)
                        {
                            $eActionAnnotation = $reflectionMethod->getAnnotation("ApiErrorAction");
                        }

                        if($eActionAnnotation->mapped)
                        {
                            if(count($eActionAnnotation->errorCodes) > 0)
                            {
                                $actionName = $eActionAnnotation->errorCodes[0];
                                $routes[$errorKey]['actions'][$actionName] = null;
                                foreach ($eActionAnnotation->errorCodes as $routeName)
                                {
                                    if(!array_key_exists($routeName, $routes[$errorKey]['actions'])) {
                                        $routes[$errorKey]['actions'][$routeName] = [
                                            "forward" => $actionName
                                        ];
                                    }
                                }
                            }
                            else
                            {
                                $actionName = $reflectionMethod->name;
                            }
                            if ($reflectionMethod->name == 'index')
                            {
                                $routes[$errorKey]["actions"][""] = $actionName;
                            }

                            $routes[$errorKey]['actions'][$actionName] = [
                                "method_name" => $reflectionMethod->name
                            ];

                            if (!$isApiError)
                            {
                                $eActionView = $eActionAnnotation->viewName ? $eActionAnnotation->viewName : $reflectionMethod->name;
                                $eActionView = file_exists(dirname($controllersFile[basename($cNamespace)])."/view/$eActionView.php") ? $eActionView : null;
                                $eLayoutTemplate = $eActionAnnotation->layoutName ? $eActionAnnotation->layoutName : $controllerLayoutTemplate;
                                $eLayoutTemplate = Configuration::get('layouts/'.$eLayoutTemplate, false) ? $eLayoutTemplate : null;
                                $routes[$errorKey]['actions'][$actionName]['view_name'] = $eActionView;
                                $routes[$errorKey]['actions'][$actionName]['layout_name'] = $eActionView ? $eLayoutTemplate : null;
                            }
                        }
                    }
                }
            }

            // remove routes without actions
            if(count($routes[$controllerRouteTemplate]['actions']) === 0)
            {
                unset($routes[$controllerRouteTemplate]);
            }
        }
        file_put_contents('app/core/config/routes_'.Configuration::$environment.'.json', json_encode($routes, JSON_PRETTY_PRINT));
        return $routes;
    }


    private static function infoParameters(\ReflectionMethod $method)
    {
        $parameters = [];
        $docComments = DocCommentParser::param($method->getDocComment());
        /**
         * @var \ReflectionParameter $reflectionParameter
         */
        foreach ($method->getParameters() as $reflectionParameter)
        {
            $docInfo = isset($docComments[$reflectionParameter->getName()]) ? DocTypeInfo::getDocType($docComments[$reflectionParameter->getName()]) : null;
            $parameters[$reflectionParameter->getPosition()] = [
                "name" => $reflectionParameter->getName(),
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
    

    // TODO : remove this and annotation and create extension
    private static function buildSiteMap($urls = [])
    {
        $host = trim(Configuration::get('environment/'.Configuration::$environment.'/host'),'/');
        $dom = new \DOMDocument();
        $dom->formatOutput = true;
        $dom->encoding = 'UTF-8';
        $dom->version = '1.0';
        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns','http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $urlset->setAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
        foreach ($urls as $url)
        {
            $url = $dom->createElement('url');
            $loc = $dom->createElement('loc');
            $loc->nodeValue = $host."/".$url;
            $lastmod = $dom->createElement('lastmod');
            $lastmod->nodeValue = date('Y-m-dTh:m:s');
            $changefreq = $dom->createElement('changefreq');
            $changefreq->nodeValue = 'Always';
            $priority = $dom->createElement('priority');
            $priority->nodeValue = 0.5;
            $url->appendChild($loc);
            $url->appendChild($lastmod);
            $urlset->appendChild($url);
        }
        $dom->appendChild($urlset);
        $dom->save(APP_PATH.'/public/sitemap.xml');
    }
}