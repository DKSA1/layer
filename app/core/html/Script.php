<?php

namespace layer\core\html;

use layer\core\utils\StringValidator;

class Script
{
    const APPLICATION_JAVASCRIPT = 'application/javascript';

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
     * @param string $scriptType
     */
    public function setType(string $scriptType)
    {
        $this->type = $scriptType;
    }

    /**
     * @param string $url
     */
    public function setSrc(string $url)
    {
        if(StringValidator::isUrl($url) || StringValidator::isPath($url))
        {
            $this->src = $url;
        }
    }

    /**
     * @param string $scriptCode
     */
    public function setCode(string $scriptCode)
    {
        $this->code = $scriptCode;
    }

    /**
     * @param string $integrity
     */
    public function setIntegrity(string $integrity)
    {
        $this->integrity = $integrity;
    }

    /**
     * @param string $crossOrigin
     */
    public function setCrossOrigin(string $crossOrigin)
    {
        $this->crossOrigin = $crossOrigin;
    }

    public function render()
    {
        if($this->src || $this->code)
        {
            $tag = '<script ';
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