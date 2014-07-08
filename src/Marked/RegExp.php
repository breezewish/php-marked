<?php

namespace Marked;

class RegExp
{
    public $source;
    public $flag;
    public $global;
    // lastIndex is not used in this project
    // public $lastIndex = 0;

    private $empty = false;

    public function __construct($source = null, $flag = '')
    {
        if ($source == null) {
            $source = '(?:)';
            $this->empty = true;
        }
        $this->source = $source;

        if (strpos($flag, 'g') !== false) {
            $this->global = true;
        } else {
            $this->global = false;
        }

        $this->flag = str_replace('g', '', $flag);
    }

    public function __toString()
    {
        return '/'.$this->source.'/'.$this->flag;
    }

    public function exec($subject)
    {
        if ($this->empty) {
            return null;
        }

        $count = preg_match($this->__toString(), $subject, $matches/*, PREG_OFFSET_CAPTURE, $this->lastIndex*/);
        
        if ($count !== false && $count !== 0) {
/*
            if ($this->global) {
                $this->lastIndex = $matches[0][1] + strlen($matches[0][0]);
            }

            $ret = array();
            foreach ($matches as $match) {
                $ret[] = $match[0];
            }

            return $ret;
*/
            return $matches;
        } else {
/*
            if ($this->global) {
                $this->lastIndex = 0;
            }
*/
            return null;
        }
    }

    public function match($subject)
    {
        if ($this->empty) {
            return null;
        }

        if ($this->global) {
            $count = preg_match_all($this->__toString(), $subject, $matches);
        } else {
            $count = preg_match($this->__toString(), $subject, $matches);
        }

        if ($count) {
            if ($this->global) {
                return $matches[0];
            } else {
                return $matches;
            }
        } else {
            return null;
        }
    }
}