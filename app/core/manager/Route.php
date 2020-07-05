<?php

namespace layer\core\manager;


class Route
{
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
    private $isError = false;

    public function __construct(string $namespace, string $action, string $method, string $routePath, array $params = [])
    {
        $this->controllerNamespace = $namespace;
        $this->controllerAction = $action;
        $this->method = $method;
        if($this->method === "*")
            $this->isError = true;
        $this->routePath = $routePath;
        $this->params = $params;
        // $this->isApi = $isApi;
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

    /**
     * @return string
     */
    public function getControllerNamespace(): string
    {
        return $this->controllerNamespace;
    }

    /**
     * @return string
     */
    public function getControllerAction(): string
    {
        return $this->controllerAction;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * @return string[]
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return bool
     */
    public function isApi(): bool
    {
        return $this->isApi;
    }

}