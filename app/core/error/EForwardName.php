<?php


namespace layer\core\error;


class EForwardName extends ELayer
{

    /**
     * @var string
     */
    private $controller;
    /**
     * @var string
     */
    private $action;
    /**
     * @var array
     */
    private $params;
    /**
     * @var
     */
    private $httpCode;

    public function __construct(string $controller, string $action, int $httpCode, array $params = [], $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
        $this->httpCode = $httpCode;
    }

    /**
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
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