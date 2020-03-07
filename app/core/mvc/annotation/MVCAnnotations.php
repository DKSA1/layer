<?php

require_once(PATH . 'app/lib/addendum/annotations.php');

// TODO : divide class in different files
class MVCAnnotation extends Annotation
{
    public function grepRouteTemplateParameters() {
        $params = [];
        preg_match('/{#?(\w+)\??}/', $this->routeTemplate, $params);
        return count($params) >= 1 ? array_slice($params, 1) : [];
    }
    public function verifyRouteTemplate() {
        if(preg_match('/^[a-zA-Z0-9\/{}?#]+$/', $this->routeTemplate)) {
            $res = $this->routeTemplate;
            $res = preg_replace('/{#(\w+)}/', '(\d+)', $res);
            $res = preg_replace('/{\/?#(\w+)\?}/', '?(\d*)', $res);
            $res = preg_replace('/{(\w+)}/', '(\w+)' , $res);
            $res = preg_replace('/{\/?(\w+)\?}/', '?(\w*)' ,$res);
            return $res;
        }
        return null;
    }
}
/** @Target("class") */
class Filter extends Annotation
{
    public $name = null;

    public $mapped = true;

    public function verifyName() {
        if(preg_match('/^[a-zA-Z0-9]+$/',$this->name))
                return $this->name;
        else
                return null;
    }
}

/** @Target("class") */
class Controller extends MVCAnnotation
{
    /**
     * @var string
     */
    public $routeTemplate;
    /**
     * @var bool
     */
    public $api = false;
    /**
     * @var string[]
     */
    public $routeNames = [];
    /**
     * @var bool
     */
    public $mapped = true;
    /**
     * @var string[]
     */
    public $filters = [];
    /**
     * @var string
     */
    public $defaultAction = 'index';
    /**
     * @var string
     */
    public $layoutName = null;

    public function verifyRouteNames() {
        $routeNames = [];
        foreach ($this->routeNames as $routeName) {
            if(preg_match('/^[a-zA-Z0-9]{1,}$/',$routeName))
                $routeNames[] = strtolower($routeName);
        }
        return $routeNames;
    }
}

/** @Target("method") */
class Action extends MVCAnnotation
{
    /**
     * @var string
     */
    public $routeTemplate;
    /**
     * @var string[]
     */
    public $routeNames = [];
    /**
     * @var \layer\core\http\IHttpMethods[]
     */
    public $methods = [\layer\core\http\IHttpMethods::POST,\layer\core\http\IHttpMethods::GET];
    /**
     * @var bool
     */
    public $api = false;
    /**
     * @var bool
     */
    public $mapped = true;
    /**
     * @var string[]
     */
    public $filters = [];
    /**
     * @var string
     */
    public $viewName = null;
    /**
     * @var string
     */
    public $layoutName = null;
    /**
     * @param $method
     * @return bool
     */
    public function hasRequestMethod($method) {
       return in_array(strtolower($method),$this->methods);
    }

    public function verifyRouteNames() {
        $routeNames = [];
        foreach ($this->routeNames as $routeName) {
            if(preg_match('/^[a-zA-Z0-9]+$/',$routeName))
               $routeNames[] = strtolower($routeName);
        }
        return $routeNames;
    }

    public function verifyMethods() {
        return array_uintersect($this->methods,\layer\core\http\IHttpMethods::ALL, function ($v1, $v2) {
            return strcasecmp($v1, $v2);
        });
    }
    
}

class ErrorController extends Annotation {
    /**
     * @var string
     */
    public $layoutName = null;
}

class ErrorAction extends Annotation {
    /**
     * @var string[]
     */
    public $errorCodes = [];
    /**
     * @var bool
     */
    public $mapped = true;
    /**
     * @var string
     */
    public $viewName = null;
    /**
     * @var string
     */
    public $layoutName = null;
}

class DefaultController extends Controller {

}

