<?php


namespace layer\core\mvc\view;


interface IView
{
    function render(array $data = NULL): string;
}