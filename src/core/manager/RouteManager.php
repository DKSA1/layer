<?php

namespace rloris\layer\core\manager;

use \rloris\layer\core\http\Response;
use rloris\layer\core\config\Configuration;
use rloris\layer\core\error\EMethod;
use rloris\layer\core\error\ERoute;
use rloris\layer\core\http\IHttpMethods;
use rloris\layer\core\route\Route;

class RouteManager
{
    // loaded from routes.json
    /**
     * @var array
     */
    private $routes;
    /**
     * @var Route
     */
    private $activeRoute;

    public function __construct($routes){
        $this->routes = $routes;
    }

    public function connect(string $method, string $route, string $controller, string $action): bool {
        if(!in_array(strtoupper($method),IHttpMethods::ALL)) {
            return false;
        }
        if($this->has($route, $method)) {
           return false;
        }
        $this->routes[strtolower($method)][$route] = $controller."@".$action;
    }

    // get the first route that match the path and returns it
    /**
     * @param string $method
     * @param string $url
     * @param null $params
     * @return Route
     */
    public function match(string $method, string $url, $params = null): Route {
        $url = trim($url, "/");
        $method = strtolower($method);
        if(array_key_exists($method, $this->routes)) {
            foreach (array_keys($this->routes[$method]) as $r) {
                // if($r === "") continue;
                if(preg_match("#^$r$#i", $url, $matches)) {
                    array_shift($matches);
                    if($params == null)
                        $params = $matches;
                    // array_intersect_key($matches, array_fill_keys(array_filter(array_keys($matches), 'is_string'),null));
                    $controller = explode('@',$this->routes[$method][$r]);
                    $this->activeRoute = new Route($controller[0], $controller[1], $method, $url, $params);
                    return $this->activeRoute;
                }
            }
        } else {
            // Method not allowed
            throw new EMethod("Requested method [{$method}] not allowed", Response::BadRequest);
        }
        // route not found
        throw new ERoute("Requested route [{$url}] not found", Response::NotFound);
    }

    /**
     * @param string $routePath
     * @param string $method
     * @return bool
     */
    public function has(string $routePath, string $method): bool {
        return array_key_exists($method, $this->routes) && array_key_exists($routePath, $this->routes[$method]);
    }

    public function isApiUrl($url): bool {
        $apiTemplate = Configuration::environment('apiRouteTemplate');
        $siteTemplate = Configuration::environment('routeTemplate');
        if($apiTemplate === false)
            return false;
        if($siteTemplate === false)
            return true;
        if($apiTemplate === $siteTemplate)
           return false;
        if(preg_match("#^$apiTemplate#i", $url))
           return true;
        return false;
    }

    /**
     * @return Route
     */
    public function getActiveRoute(): Route
    {
        return $this->activeRoute;
    }
}