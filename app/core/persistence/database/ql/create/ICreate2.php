<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 26-01-19
 * Time: 21:58
 */

namespace layer\core\persistence\database\ql\create;


interface ICreate2
{
    function ifNotExists() : ICreate3;
}