<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 15-12-18
 * Time: 13:30
 */

namespace layer\core\persistence\database\ql\select;


interface ISelect6
{

    public function limit() : ISelect7;

    public function offset();
}