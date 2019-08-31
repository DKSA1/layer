<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 13-12-18
 * Time: 18:49
 */

namespace layer\core\persistence;


interface IPersistence
{
    const FILE = "file";
    const DATABASE = "database";
    const MOCK = 'mock';

    const DATABASE_STATEMENT = "statement";
    const DATABASE_ATTRIBUTE = "attribute";
    const DATABASE_FETCH_MODE = "fetch_mode";
    const DATABASE_FETCH_CLASS = "fetch_class";

    const FETCH_MODE_OBJECT  = "object";
    const FETCH_MODE_ASSOC   = "assoc";

    function create($params);
    function read($params);
    function update($params);
    function delete($params);

}