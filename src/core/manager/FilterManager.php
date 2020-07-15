<?php

namespace rloris\layer\core\manager;

use rloris\layer\core\mvc\filter\Filter;

class FilterManager
{
    /**
     * @var array
     */
    private $filters;
    /**
     * @var string[]
     */
    private $globals;
    /**
     * @var Filter[]
     */
    private $instances;
    /**
     * @var string[]
     */
    private $actives;

    public function __construct($filters, $globals)
    {
        $this->filters = $filters;
        $this->globals = $globals;
        $this->actives = $globals;
        $this->instances = [];
    }

    // checks if filter exists from it's name
    public function exists($name) {
        return array_key_exists($name, $this->filters);
    }

    public function clear() {
        $this->actives = [];
    }

    public function add($name, $position = null): bool {
        if(!in_array($name, $this->actives) && $this->exists($name)) {
            if($position) {
                array_splice($this->actives, $position, 0, $name);
            } else
                array_push($this->actives, $name);
            return true;
        }
        return false;
    }

    public function remove($name): bool {
        if(in_array($name, $this->actives)) {
            $idx = array_search($name, $this->actives);
            if($idx !== false) {
                array_splice($this->actives, $idx, 1);
                return true;
            }
        }
        return false;
    }

    public function isActive($name) {
        return in_array($name, $this->actives);
    }

    public function run(bool $in) {
        if(!$in)
            $this->actives = array_reverse($this->actives);
        foreach ($this->actives as $name) {
            if(!array_key_exists($name, $this->instances)) {
                $filterClass = $this->filters[$name]['namespace'];
                if(!class_exists($filterClass)) {
                    require_once $this->filters[$name]['path'];
                }
                $this->instances[$name] = new $filterClass();
            }
            if($in) {
                $result = $this->instances[$name]->in();
            } else {
                $result = $this->instances[$name]->out();
            }
            if($result) {

            }
        }
    }

}