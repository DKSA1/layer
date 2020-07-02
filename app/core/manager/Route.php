<?php

namespace layer\core\manager;


class Route
{
    // TODO add alias
    private $alias;
    /**
     * @var string
     */
    private $routePath;
    /**
     * @var string
     */
    private $method;
    /**
     * @var string
     */
    private $controllerNamespace;
    /**
     * @var string
     */
    private $controllerAction;
    /**
     * @var string[] $params
     */
    private $params;
    /**
     * @var bool
     */
    private $isApi;
    /**
     * @var bool
     */
    private $isError;
    /**
     * @var bool
     */
    private $hasView;
    /**
     * @var bool
     */
    private $hasLayout;
    /**
     * @var string[] $filters
     */
    private $filters;
    /**
     * @var string
     */
    private $controllerFile;

    public function __construct(string $namespace, string $action, string $method, string $routePath, array $params = [])
    {
        $this->controllerNamespace = $namespace;
        $this->controllerAction = $action;
        $this->method = $method;
        $this->routePath = $routePath;
        $this->params = $params;
    }

    public function run() {
        $c = ControllerManager::getInstance();
        $c->run($this->controllerNamespace, $this->controllerAction, $this->params, $this->method === '*' ? true : false);
    }

    /**
     * @return string
     */
    public function getRoutePath()
    {
        return $this->routePath;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

}