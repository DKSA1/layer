<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 15-12-18
 * Time: 13:27
 */

namespace layer\core\persistence\ql\select;



interface ISelect2
{

    public function innerJoin() : ISelect2;

    public function leftJoin() : ISelect2;

    public function rightJoin() : ISelect2;

    public function fullJoin() : ISelect2;

    public function where() : ISelect3;

    public function groupBy() : ISelect4;

    public function orderBy() : ISelect6;

    public function limit() : ISelect7;

    public function offset();

}