<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 01:49
 */

namespace layer\core\persistence\database\ql\create;


interface ICreate5
{
    function primaryKey() : ICreate4;

    function foreignKey() : ICreate6;
}