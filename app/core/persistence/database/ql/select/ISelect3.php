<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 15-12-18
 * Time: 13:28
 */

namespace layer\core\persistence\database\ql\select;
use layer\core\persistence\database\ql\operator\logical\ILogical;

interface ISelect3 extends ILogical
{
    public function groupBy() : ISelect4;

    public function orderBy() : ISelect6;

    public function limit() : ISelect7;

    public function offset();

    //operators

    function _not_() : ISelect3;

    function _like_() : ISelect3;

    function _in_() : ISelect3;

    function _between_() : ISelect3;

    function _all_() : ISelect3;

    function _any_() : ISelect3;

    function _exists_() : ISelect3;

    function _some_() : ISelect3;

    function _and_() : ISelect3;

    function _or_() : ISelect3;


}