<?php

namespace layer\core\error;

use Throwable;

class EForward extends ELayer
{
    /**
     * @var string
     */
    private $internalRoute;

    public function __construct($internalRoute, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->internalRoute = $internalRoute;
    }

    /**
     * @return string
     */
    public function getInternalRoute(): string
    {
        return $this->internalRoute;
    }
}