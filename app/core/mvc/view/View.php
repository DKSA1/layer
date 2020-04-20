<?php

namespace layer\core\mvc\view;

use Closure;

class View implements IView
{
    private $viewTemplate;
    private $viewData = [];

    public function __construct(string $viewTemplate)
    {
        $this->setViewTemplate($viewTemplate);
    }

    public function setViewTemplate(string $viewTemplate)
    {
        if(is_file($viewTemplate) && is_readable($viewTemplate))
        {
            $this->viewTemplate = $viewTemplate;
        }
    }

    public function getViewTemplate()
    {
        return $this->viewTemplate;
    }

    public function render(array $data = NULL): string
    {
        if($data)
        {
            $this->viewData = $data;
        }

        ob_start();

        require_once $this->viewTemplate;

        return ob_get_clean();
    }

    public function __set($name, $value)
    {
        $this->viewData[$name] = $value;
        return $this;
    }

    public function __get($name)
    {
        if (!isset($this->viewData[$name]))
        {
            return null;
        }
        $data = $this->viewData[$name];
        return $data instanceof Closure ? $data($this) : $data;
    }

    public function __isset($name)
    {
        return isset($this->viewData[$name]);
    }

    public function __unset($name)
    {
        if (!isset($this->viewData[$name]))
        {
            return null;
        }
        unset($this->viewData[$name]);
        return $this;
    }

}