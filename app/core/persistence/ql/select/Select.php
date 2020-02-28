<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 15-12-18
 * Time: 13:25
 */

namespace layer\core\persistence\ql\select;

use layer\core\persistence\ql\operator\logical\ICondition;
use layer\core\persistence\ql\operator\logical\ILogical;

class Select implements ISelect1,ISelect2,ISelect3,ISelect4,ISelect5,ISelect6,ISelect7
{

    /**
     * @return ISelect2
     */
    public function from() : ISelect2 {

        return $this;
    }

    /**
     * @return ISelect2
     */
    public function innerJoin() : ISelect2
    {
        return $this;
    }

    /**
     * @return ISelect2
     */
    public function leftJoin() : ISelect2
    {
        return $this;
    }

    /**
     * @return ISelect2
     */
    public function rightJoin() : ISelect2
    {
        return $this;
    }

    /**
     * @return ISelect2
     */
    public function fullJoin() : ISelect2
    {

        return $this;
    }

    /**
     * @return ISelect3
     */
    public function where() : ISelect3
    {
        return $this;
    }

    /**
     * @return ISelect4
     */
    public function groupBy() : ISelect4
    {
        return $this;
    }

    /**
     * @return ISelect5
     */
    public function having() : ISelect5
    {
        return $this;
    }

    /**
     * @return ISelect6
     */
    public function orderBy() : ISelect6
    {
        return $this;
    }

    /**
     * @return ISelect7
     */
    public function limit() : ISelect7
    {
        return $this;
    }

    public function offset(){

        return $this;
    }

    //operators

    function condition() : ISelect3
    {
       return $this;
    }

    function not(): ISelect3
    {
        return $this;
        // TODO: Implement not() method.
    }

    function _and_(): ISelect3
    {
        return $this;
        // TODO: Implement _and_() method.
    }

    function _or_(): ISelect3
    {
        return $this;
        // TODO: Implement _or_() method.
    }


    function _not_(): ISelect3
    {
        // TODO: Implement _not_() method.
    }

    function _like_(): ISelect3
    {
        // TODO: Implement _like_() method.
    }

    function _in_(): ISelect3
    {
        // TODO: Implement _in_() method.
    }

    function _between_(): ISelect3
    {
        // TODO: Implement _between_() method.
    }

    function _all_(): ISelect3
    {
        // TODO: Implement _all_() method.
    }

    function _any_(): ISelect3
    {
        // TODO: Implement _any_() method.
    }

    function _exists_(): ISelect3
    {
        // TODO: Implement _exists_() method.
    }

    function _some_(): ISelect3
    {
        // TODO: Implement _some_() method.
    }
}