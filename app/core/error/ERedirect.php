<?php
namespace layer\core\error;

use layer\core\http\IHttpCodes;
use Throwable;

class ERedirect extends ELayer
{
    /**
     * @var int
     */
    private $httpCode;
    /**
     * @var string
     */
    private $location;

    public function __construct($location, $httpCode = IHttpCodes::MovedTemporarily, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->location = $location;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

}