<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 13:35
 */

namespace layer\core\persistence\ql\update;


interface IUpdate2
{
    function keyValue() : IUpdate2;

    function where();
}