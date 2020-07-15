<?php

namespace rloris\layer\core\mvc\view;

class Layout implements IView
{
    /**
     * @var View[]
     */
    protected $views = [];

    public function __construct(){}

    public function appendView(IView $view): Layout
    {
        if(!in_array($view, $this->views, true))
        {
            $this->views[] = $view;
        }
        return $this;
    }

    public function removeView(IView $view): Layout
    {
        $this->views = array_filter($this->views, function ($v) use ($view) {
            return $v !== $view;
        });
        return $this;
    }

    public function render(array $data = NULL): string
    {
        ob_start();

        foreach ($this->views as $view)
        {
            echo $view->render($data);
        }

        return ob_get_clean();
    }

}