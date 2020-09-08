<?php

namespace rloris\layer\core\error;
use Error;
use Throwable;

class ELayer extends Error
{
    private $payload;

    public function __construct($message = "", $code = 0, $payload = null, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->payload = $payload;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }


}