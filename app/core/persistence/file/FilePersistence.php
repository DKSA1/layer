<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 13-12-18
 * Time: 19:41
 */

namespace layer\core\persistence\file;


use layer\core\persistence\IPersistence;
use layer\core\persistence\Persistence;

class FilePersistence extends Persistence
{
    public $type = IPersistence::FILE;


    public function __construct($options)
    {
    }

    protected function open()
    {
        // TODO: Implement connect() method.
    }

    function create($params)
    {
        // TODO: Implement create() method.
    }

    function read($params)
    {
        // TODO: Implement read() method.
    }

    function update($params)
    {
        // TODO: Implement update() method.
    }

    function delete($params)
    {
        // TODO: Implement delete() method.
    }

    protected function close()
    {
        // TODO: Implement disconnect() method.
    }
}