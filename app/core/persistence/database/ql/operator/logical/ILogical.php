<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 13:59
 */
namespace layer\core\persistence\database\ql\operator\logical;

interface ILogical
{
    function _not_() ;

    function _like_() ;

    function _in_() ;

    function _between_() ;

    function _all_() ;

    function _any_() ;

    function _exists_() ;

    function _some_() ;

    function _and_() ;

    function _or_() ;

}