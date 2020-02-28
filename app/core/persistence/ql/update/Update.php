<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 13:29
 */

namespace layer\core\persistence\ql\update;


class Update implements IUpdate1,IUpdate2
{

    function set(): IUpdate2
    {
        return $this;
    }

    function keyValue(): IUpdate2
    {
        return $this;
    }

    function where()
    {
        return $this;
    }


}