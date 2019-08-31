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

}

/** @Target("class") */
class Controller extends Annotation implements MvcAnnotation
{
    /**
     * @var bool
     */
    public $api = false;
    /**
     * @var string
     */
    public $routeName = null;
    /**
     * @var bool
     */
    public $mapped = true;
    /**
     * @var string[]
     */
    public $filters = [];
}

/** @Target("method") */
class Action extends Annotation implements MvcAnnotation
{
    /**
     * @var string
     */
    public $routeName = null;
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
    
}

