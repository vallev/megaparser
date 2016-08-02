<?php

namespace parserbot\megaparser;

class Query {

    protected $class;

    public function __construct($class)
    {
        $this->class = $class;
    }

    public function all()
    {
        return [new $this->class()];
    }

    public function one()
    {
        return new $this->class();
    }

    public function where($condition)
    {
        return new Query($this->class);
    }

    public function orderBy($condition)
    {
        return new Query($this->class);
    }
}