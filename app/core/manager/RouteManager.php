<?php

namespace layer\core\manager;

use layer\core\config\Configuration;
use layer\core\error\EForward;
use layer\core\error\ELayer;
use layer\core\error\EMethod;
use layer\core\error\ERedirect;
use layer\core\error\ERoute;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpMethods;
use layer\core\http\Response;

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

    /**
     * @var RouteManager
     */
    private static $instance;
    /**
     * @var string
     */
    private $url;
    /**
     * @var string
     */
    private $method;
    /**
     * @var ELayer
     */
    private $error;

    public static function getInstance($routes) : RouteManager
    {
        if(self::$instance == null) self::$instance = new RouteManager($routes);
        return self::$instance;
    }

    private function __construct($routes){
        $this->routes = $routes;
    }


    public function add(string $method, string $route, string $controller, string $action): bool {
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
     * @return Route
     */
    private function match(string $method, string $url): Route {
        $this->url = trim($url, "/");
        $this->method = strtolower($method);
        $params = null;
        if(array_key_exists($this->method, $this->routes)) {
            foreach (array_keys($this->routes[$this->method]) as $r) {
                // if($r === "") continue;
                if(preg_match("#^$r$#i", $this->url, $matches)) {
                    array_shift($matches);
                    $params = array_intersect_key($matches, array_fill_keys(array_filter(array_keys($matches), 'is_string'),null));
                    $controller = explode('@',$this->routes[$this->method][$r]);
                    return new Route($controller[0], $controller[1], $this->method, $this->url, $this->method === "*" ? ["e" => $this->error] : $params);
                }
            }
        } else {
            // Method not allowed
            throw new EMethod("Requested method {$this->method} not allowed", HttpHeaders::BadRequest);
        }
        // route not found
        throw new ERoute("Requested route {$url} not found", HttpHeaders::NotFound);
    }

    /**
     * @param string $routePath
     * @param string $method
     * @return bool
     */
    public function has(string $routePath, string $method): bool {
        return array_key_exists($method, $this->routes) && array_key_exists($routePath, $this->routes[$method]);
    }

    /**
     * Perfom the action attached to the match route
     * @param string $url
     * @param string $method
     * @return bool
     */
    public function run(string $url, string $method): bool {
        try {
            $this->activeRoute = $this->match($method, $url);
            $this->activeRoute->run();
            return true;
        } catch(EForward $e) {
            return $this->forward($e);
        } catch(ERedirect $e) {
            return $this->redirect($e);
        } catch(ELayer $e) {
            return $this->error($e);
        }
    }

    private function redirect($e): bool {

        return true;
    }

    private function forward($e): bool {

        return true;
    }

    private function error(ELayer $e): bool {
        $this->error = $e;
        $apiTemplate = Configuration::get('environment/'.Configuration::$environment.'/apiRouteTemplate');
        $template = Configuration::get('environment/'.Configuration::$environment.'/routeTemplate');
        if(preg_match("#^$apiTemplate#i", $this->url)) {
            // api
            $route = trim($apiTemplate, "/")."/".$e->getCode();
        } else {
            $route = trim($template, "/")."/".$e->getCode();
        }
        $route = trim($route, '/');
        $errorRoute = $this->match('*', $route);
        Response::getInstance()->setResponseCode($e->getCode());
        if($errorRoute) {
            $errorRoute->run();
        }
        return true;
    }
}