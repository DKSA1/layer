<?php

use layer\core\http\IHttpMethods;
use layer\core\http\IHttpContentType;

require_once(PATH . 'app/lib/addendum/annotations.php');

// TODO : divide class in different files

abstract class MVCAnnotation extends Annotation
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
    /**
     * @var string $name
     */
    public $name = null;
    /**
     * @var bool $mapped
     */
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
    public $methods = [IHttpMethods::POST, IHttpMethods::GET];
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

    public function verifyMethods() {
        $this->methods = array_map('strtolower', $this->methods);
        sort($this->methods);
        return array_uintersect($this->methods, IHttpMethods::ALL, function ($v1, $v2) {
            return strcasecmp($v1, $v2);
        });
    }
    
}

/** @Target("class") */
class ErrorController extends Annotation {
    /**
     * @var string
     */
    public $layoutName = null;
}

/** @Target("method") */
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

/** @Target("class") */
class DefaultController extends Controller {

}

/** @Target("class") */
class ApiController extends MVCAnnotation
{
    /**
     * @var string
     */
    public $routeTemplate;
    /**
     * @var bool
     */
    public $mapped = true;
    /**
     * @var string[]
     */
    public $filters = [];
    /**
     * @var string $responseType
     */
    public $responseType = 'JSON';
    /**
     * @var string
     */
    public $defaultAction = '/';
}

/** @Target("method") */
class ApiAction extends MVCAnnotation
{
    /**
     * @var string
     */
    public $routeTemplate;
    /**
     * @var string[]
     */
    public $methods = [IHttpMethods::GET];
    /**
     * @var bool
     */
    public $mapped = true;
    /**
     * @var string[]
     */
    public $filters = [];
    /**
     * @var string $responseType
     */
    public $responseType = 'JSON';

    public function verifyMethods() {
        $this->methods = array_map('strtolower', $this->methods);
        sort($this->methods);
        return array_uintersect($this->methods, IHttpMethods::ALL, function ($v1, $v2) {
            return strcasecmp($v1, $v2);
        });
    }
}


/** @Target("class") */
class ApiErrorController extends Annotation {}

/** @Target("method") */
class ApiErrorAction extends Annotation {
    /**
     * @var string[]
     */
    public $errorCodes = [];
    /**
     * @var bool
     */
    public $mapped = true;
}



