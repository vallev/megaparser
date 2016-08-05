<?php

namespace parserbot\megaparser;

class GeneratorIterator extends \ArrayIterator {
    private $iterators;
    private $current = false;

    public function prepend(\Iterator $iterator)
    {
        foreach ($this->iterators as $key=>$it) {
            if (!$it->valid()) {
                unset($this->iterators[$key]);
            }
        }

        array_unshift($this->iterators, $iterator);
        $this->current = 0;
    }

    /*
     *  Возвращаем текущий внутренний итератор, или NULL если кончились
     *
     */
    public function currentIterator()
    {

        $return = NULL;

        foreach ($this->iterators as $key=>$iterator) {
            if (!$iterator->valid()) {
                unset($this->iterators[$key]);
            }

            if ($iterator->valid() && !$return) {
                $return = $iterator;
            }
        }

        return $iterator;
    }

    function __construct()
    {
        $this->iterators = [];
    }

    /*
     * Возвращаем следующий элемент текущего итератора или NULL если они кончились
     */

    function next()
    {

        $iterator = $this->currentIterator();

        if ($iterator !== NULL) {
            $iterator->next();
        }
    }

    function valid()
    {
        foreach ($this->iterators as $key => $iterator) {
            if ($iterator->valid()) {
                return true;
            }
        }
        return false;
    }

    function append($it)
    {
        $this->iterators[] = $it;
    }


    function rewind()
    {
        /*$this->iterators->rewind();
        if ($this->iterators->valid())
        {
            $this->getInnerIterator()->rewind();
        }*/
    }

    function current()
    {
        $iterator = $this->currentIterator();

        if ($iterator !== NULL) {
            return $iterator->valid() ? $iterator->current() : NULL;
        } else {
            return NULL;
        }
    }


    /*function key()
    {
        return $this->iterators->valid() ? $this->getInnerIterator()->key() : NULL;
    }*/

    function getInnerIterator()
    {
        return $this->iterators->current();
    }


    function __call($func, $params)
    {
        return call_user_func_array(array($this->currentIterator(), $func), $params);
    }


}