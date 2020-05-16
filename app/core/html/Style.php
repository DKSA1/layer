<?php

namespace layer\core\html;

use layer\core\utils\StringValidator;

class Style
{
    const STYLESHEET = 'stylesheet';

    private $rel;
    private $href;
    private $code;
    private $integrity;
    private $crossOrigin;

    public function __construct($url = null, $rel = null)
    {
        if($rel)
        {
            $this->setRel($rel);
        }
        if($url)
        {
            $this->setHref($url);
        }
    }

    /**
     * @param string $rel
     */
    public function setRel(string $rel)
    {
        $this->rel = $rel;
    }

    /**
     * @param string $href
     */
    public function setHref(string $href)
    {
        if(StringValidator::isUrl($href) || StringValidator::isPath($href))
        {
            $this->href = $href;
        }
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

    /**
     * @param string $code
     */
    public function setCode(string $code)
    {
        $this->code = $code;
    }

    public function render()
    {
        if($this->href || $this->code)
        {
            $tag = '<link';
            if($this->rel) $tag .= ' rel="'.$this->rel.'" ';
            if($this->href) $tag .= ' href="'.$this->href.'" ';
            if($this->integrity) $tag .= " integrity='".$this->integrity."' ";
            if($this->crossOrigin) $tag .= " crossorigin='".$this->crossOrigin."' ";
            $tag .= ">";
            if($this->code) $tag .= $this->code;
            $tag .= "</link>";
            return $tag;
        }
        return null;
    }
}