<?php

namespace layer\core\mvc\view;

use layer\core\config\Configuration;

class ViewManager
{
    /**
     * @var string $viewDirectory
     */
    private $viewDirectory;
    /**
     * @var string $layoutName
     */
    private $layoutName;
    /**
     * @var string[] $beforeViews
     */
    private $beforeViews;
    /**
     * @var string $contentView
     */
    private $contentView;
    /**
     * @var string[] $afterViews
     */
    private $afterViews;
    /**
     * @var string[]
     */
    private $shared;

    public static function build($pathToView, $shared) {
        if(file_exists($pathToView))
        {
            return new ViewManager($pathToView, $shared);
        }
        return null;
    }

    private function __construct($pathToView, $shared)
    {
        $this->shared = $shared;
        $this->afterViews = [];
        $this->beforeViews = [];
        $this->contentView = basename($pathToView,'.php');
        $this->viewDirectory = dirname($pathToView);
    }

    private function loadLayout($layoutName)
    {
        if(Configuration::get("layouts/$layoutName", false))
        {
            $this->layoutName = $layoutName;
            $this->beforeViews = Configuration::get("layouts/$layoutName/before", false);
            $this->afterViews = Configuration::get("layouts/$layoutName/after", false);
            return true;
        }
        return false;
    }
    /**
     * @return string
     */
    public function getLayoutName(): string
    {
        return $this->layoutName;
    }

    /**
     * @param string $layoutName
     */
    public function setLayoutName(string $layoutName)
    {
        return $this->loadLayout($layoutName);
    }

    public function addBeforeView($viewName, $position = null) {
        if($this->sharedViewExists($viewName))
        {
            if($position)
            {
                $this->beforeViews[$position] = $viewName;
            }
            else
            {
                $this->beforeViews[] = $viewName;
            }
            return true;
        }
        return false;
    }

    public function removeBeforeView($viewName) {
        $idx = array_search($viewName, $this->beforeViews);
        if($idx)
        {
            array_splice($this->beforeViews, $idx, 1);
            return true;
        }
        return false;
    }

    /**
     * @return string[]
     */
    public function getBeforeViews(): array
    {
        return $this->beforeViews;
    }

    /**
     * @return string
     */
    public function getContentView(): string
    {
        return $this->contentView;
    }

    /**
     * @param string $contentView
     */
    public function setContentView(string $contentView)
    {
        if(file_exists($this->viewDirectory."/$contentView.php"))
        {
            $this->contentView = $contentView;
            return true;
        }
        return false;
    }

    public function addAfterView($viewName, $position = null) {
        if($this->sharedViewExists($viewName))
        {
            if($position)
            {
                $this->afterViews[$position] = $viewName;
            }
            else
            {
                $this->afterViews[] = $viewName;
            }
            return true;
        }
        return false;
    }

    public function removeAfterView($viewName) {
        $idx = array_search($viewName, $this->afterViews);
        if($idx)
        {
            array_splice($this->afterViews, $idx, 1);
            return true;
        }
        return false;
    }
    /**
     * @return string[]
     */
    public function getAfterViews(): array
    {
        return $this->afterViews;
    }

    private function sharedViewExists($viewName) {
        if(array_key_exists($viewName, $this->shared) && file_exists($this->shared[$viewName]))
        {
            return true;
        }
        return false;
    }

    protected function generateView($data = [])
    {
        $compositeView = new Layout();
        foreach ($this->beforeViews as $view) {
            $compositeView->appendView(new View($this->shared[$view]));
        }
        $compositeView->appendView(new View($this->viewDirectory."/".$this->contentView.".php"));
        foreach ($this->afterViews as $view) {
            $compositeView->appendView(new View($this->shared[$view]));
        }
        return $compositeView->render($data);
    }
}