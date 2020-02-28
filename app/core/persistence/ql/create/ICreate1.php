<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 01:30
 */

namespace layer\core\persistence\database\ql\create;


interface ICreate1
{
    function database();

    function table() : ICreate2;

    function index() : ICreate7;

    function uniqueIndex() : ICreate7;
}