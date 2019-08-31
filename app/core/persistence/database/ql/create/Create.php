<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 01:29
 */

namespace layer\core\persistence\database\ql\create;

class Create implements ICreate1, ICreate3, ICreate4, ICreate5, ICreate6,ICreate7
{

    function database()
    {
        return $this;
    }

    function table() : ICreate3
    {
        return $this;
    }

    function index() : ICreate7
    {
        return $this;
    }

    function column() : ICreate3
    {
        return $this;
    }

    function constraint() : ICreate5
    {
        return $this;
    }

    function primaryKey(): ICreate4
    {
        return $this;
    }

    function foreignKey(): ICreate6
    {
        return $this;
    }

    function references(): ICreate4
    {
        return $this;
    }

    function uniqueIndex() : ICreate7
    {
        return $this;
    }

    function on()
    {
        return $this;
    }
}