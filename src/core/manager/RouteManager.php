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
    /**
     * @var array
     */
    private $routes;
    /**
     * @var Route
     */
    private $activeRoute;

    public function __construct($routes)
    {
        $this->routes = $routes;
    }

    /**
     * @param string $method
     * @param string $url
     * @param null $params
     * @return Route
     */
    public function match(string $method, string $url, $params = null): Route
    {
        $url = trim($url, "/");
        $method = strtolower($method);
        if(array_key_exists($method, $this->routes)) {
            if(($r = $this->has($url, $method, $matches)))
            {
                if($params == null)
                    $params = $matches;
                $controller = explode('@',$this->routes[$method][$r]);
                $this->activeRoute = new Route($controller[0], $controller[1], $method, $url, $params);
                return $this->activeRoute;
            }
            else
            {
                // route not found
                throw new ERoute("Requested route [{$url}] not found", Response::NotFound);
            }
        } else {
            // Method not allowed
            throw new EMethod("Requested method [{$method}] not allowed", Response::BadRequest);
        }
    }

    /**
     * @param string $routePath
     * @param string $method
     * @param array $matches
     * @return bool|string
     */
    public function has(string $routePath, string $method, & $matches = null)
    {
        if(array_key_exists($method, $this->routes))
        {
            foreach (array_keys($this->routes[$method]) as $r)
            {
                if (preg_match("#^$r$#i", $routePath, $matches, 512))
                {
                    if($matches !== null) array_shift($matches);
                    return $r;
                }
            }
        }
        return false;
    }

    /**
     * @param $url
     * @return bool
     */
    public function isApiUrl($url): bool
    {
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