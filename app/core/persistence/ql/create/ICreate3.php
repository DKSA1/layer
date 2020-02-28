<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 01:34
 */

namespace layer\core\persistence\database\ql\create;


interface ICreate3
{
    function column() : ICreate3;

    function constraint() : ICreate5;
}