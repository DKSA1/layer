<?php
namespace layer\core\exception;

use layer\core\http\HttpHeaders;
use Throwable;

class ForwardException extends \Exception
{
    /**
     * @var int
     */
    private $forwardHttpCode;
    /**
     * @var string
     */
    private $forwardLocation;

    public function __construct($httpCode, $location, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->forwardHttpCode = $httpCode;
        $this->forwardLocation = $location;
    }

    /**
     * @return int
     */
    public function getForwardHttpCode(): int
    {
        return $this->forwardHttpCode;
    }

    /**
     * @return string
     */
    public function getForwardLocation(): string
    {
        return $this->forwardLocation;
    }

}