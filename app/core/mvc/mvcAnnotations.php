<?php

require_once(PATH . 'app/lib/addendum/annotations.php');


interface MvcAnnotation
{

}

/** @Target("class") */
class Filter extends Annotation implements MvcAnnotation
{
    public $name = null;

    public $mapped = true;

    public function verifyName() {
        if(preg_match('/^[a-zA-Z0-9]{1,}$/',$this->name))
                return $this->name;
        else
                return null;
    }
}

/** @Target("class") */
class Controller extends Annotation implements MvcAnnotation
{
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

    public function verifyRouteNames() {
        $routeNames = [];
        foreach ($this->routeNames as $routeName) {
            if(preg_match('/^[a-zA-Z0-9]{1,}$/',$routeName))
                $routeNames[] = $routeName;
        }
        return $routeNames;
    }
}

/** @Target("method") */
class Action extends Annotation implements MvcAnnotation
{
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
     * @var bool
     */
    public $usePartialViews = true;
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
            if(preg_match('/^[a-zA-Z0-9]{1,}$/',$routeName))
               $routeNames[] = $routeName;
        }
        return $routeNames;
    }

    public function verifyMethods() {
        return array_uintersect($this->methods,\layer\core\http\IHttpMethods::ALL, function ($v1, $v2) {
            return strcasecmp($v1, $v2);
        });
    }
    
}

