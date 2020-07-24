<?php

namespace rloris\layer\core\error;

use Throwable;

class EForwardURL extends ELayer
{
    /**
     * @var string
     */
    private $internalRoute;
    /**
     * @var string
     */
    private $method;
    /**
     * @var array
     */
    private $params;
    /**
     * @var
     */
    private $httpCode;

    public function __construct(string $internalRoute, string $method, int $httpCode, array $params, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->internalRoute = $internalRoute;
        $this->method = $method;
        $this->params = $params;
        $this->httpCode = $httpCode;
    }

    /**
     * @return string
     */
    public function getInternalRoute(): string
    {
        return $this->internalRoute;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}