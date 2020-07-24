<?php

use rloris\layer\core\http\IHttpMethods;

// require_once(APP_PATH . 'src/lib/addendum/annotations.php');
require_once(dirname(__DIR__, 3)."/lib/addendum/annotations.php");

// TODO : divide class in different files

abstract class RouteAnnotation extends Annotation
{
    private function grepRouteTemplateParameters() {
        $params = [];
        preg_match_all('/{#?(\w+)\??}/', $this->routeTemplate, $params);
        return count($params) >= 1 ? array_slice($params, 1)[0] : [];
    }

    public function validateRouteTemplate() {
        if(preg_match('/^[a-zA-Z0-9\/{}?#]+$/', $this->routeTemplate)) {
            $res = $this->routeTemplate;
            foreach ($this->grepRouteTemplateParameters() as $param) {
                // (\w+)
                $res = preg_replace("/{#".$param."}/", "(?<".$param.">\d+)", $res);
                $res = preg_replace("/{\/?#".$param."\?}/", "?(?<".$param.">\d*)", $res);
                $res = preg_replace("/{".$param."}/", "(?<".$param.">[^/]+)" , $res);
                $res = preg_replace("/{\/?".$param."\?}/", "?(?<".$param.">[^/]*)" ,$res);
            }
            return $res;
        }
        return null;
    }

    public function getFilters() {
        return array_map('strtolower', $this->filters);
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

    public function validateName() {
        if(preg_match('/^[a-zA-Z0-9]+$/',trim($this->name)))
                return trim($this->name);
        else
                return null;
    }
}

/** @Target("class") */
class GlobalFilter extends Filter
{

}

/** @Target("class") */
class Controller extends RouteAnnotation
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
class Action extends RouteAnnotation
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
    public function hasRequestMethod($method)
    {
       return in_array(strtolower($method),$this->methods);
    }

    public function validateMethods()
    {
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
class ApiController extends RouteAnnotation
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
class ApiAction extends RouteAnnotation
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

    public function validateMethods() {
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




