<?php


namespace layer\core\mvc\view;


interface IView
{
    public function render(array $data = NULL): string;
}