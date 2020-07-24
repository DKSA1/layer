<?php

namespace rloris\layer\core\html;

use rloris\layer\utils\StringValidator;

class Script
{
    const APPLICATION_JAVASCRIPT = 'application/javascript';
    private $isAsync;
    private $isDefer;
    private $type;
    private $src;
    private $code;
    private $integrity;
    private $crossOrigin;

    public function __construct($url = null, $type = null)
    {
        if($type)
        {
            $this->setType($type);
        }
        if($url)
        {
            $this->setSrc($url);
        }
    }

    /**
     * @param bool $v
     * @return Script
     */
    public function setAsync(bool $v): Script {
        $this->isAsync = $v;
    }

    /**
     * @param bool $v
     * @return Script
     */
    public function setDefer(bool $v): Script {
        $this->isDefer = $v;
    }

    /**
     * @param string $scriptType
     * @return Script
     */
    public function setType(string $scriptType): Script
    {
        $this->type = $scriptType;
        return $this;
    }

    /**
     * @param string $url
     * @return Script
     */
    public function setSrc(string $url): Script
    {
        if(StringValidator::isUrl($url) || StringValidator::isPath($url))
        {
            $this->src = $url;
        }
        return $this;
    }

    /**
     * @param string $scriptCode
     * @return Script
     */
    public function setCode(string $scriptCode): Script
    {
        $this->code = $scriptCode;
        return $this;
    }

    /**
     * @param string $integrity
     * @return Script
     */
    public function setIntegrity(string $integrity): Script
    {
        $this->integrity = $integrity;
        return $this;
    }

    /**
     * @param string $crossOrigin
     * @return Script
     */
    public function setCrossOrigin(string $crossOrigin): Script
    {
        $this->crossOrigin = $crossOrigin;
        return $this;
    }

    public function render()
    {
        if($this->src || $this->code)
        {
            $tag = '<script ';
            if($this->isAsync) $tag.= " async ";
            else if($this->isDefer) $tag.= " defer ";
            if($this->type) $tag .= " type='".$this->type."' ";
            if($this->src) $tag .= " src='".$this->src."' ";
            if($this->integrity) $tag .= " integrity='".$this->integrity."' ";
            if($this->crossOrigin) $tag .= " crossorigin='".$this->crossOrigin."' ";
            $tag .= ">";
            if($this->code) $tag .= $this->code;
            $tag .= "</script>";
            return $tag;
        }
        return null;
    }
}