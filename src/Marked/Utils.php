<?php

namespace Breezewish\Marked;

use Breezewish\Marked\RegExp;

class Utils
{
    public static function str_replace_once($search, $replace, $subject)
    {
        $firstChar = strpos($subject, $search);

        if ($firstChar !== false) {
            $beforeStr = substr($subject, 0, $firstChar);
            $afterStr = substr($subject, $firstChar + strlen($search));
            return $beforeStr.$replace.$afterStr;
        } else {
            return $subject;
        }
    }

    public static function js_regex_replace($str, RegExp $regex, $replace)
    {
        return preg_replace(strval($regex), $replace, $str, $regex->global ? -1 : 1);
    }

    public static function js_replace($str, $search, $replace)
    {
        if ($search instanceof RegExp) {
            return self::js_regex_replace($str, $search, $replace);
        } else {
            return self::str_replace_once($search, $replace, $str);
        }
    }

    public static function replace($regex, $param_1 = null, $param_2 = null)
    {
        $regex = $regex->source;

        if (is_array($param_1)) {
            $opt = '';
            $arr = $param_1;
        } else {
            $opt = $param_1;
            $arr = $param_2;
        }

        foreach ($arr as $pair) {
            $name = $pair[0];
            $val = $pair[1];

            if ($val instanceof RegExp) {
                $val = $val->source;
            }
            $val = preg_replace('/(^|[^\\[])\\^/', '$1', $val);
            $regex = self::js_replace($regex, $name, $val);
        }

        return new RegExp($regex, $opt);
    }

    public static function escape($html, $encode = false)
    {
        if (!$encode) {
            $html = preg_replace('/&(?!#?\\w+;)/', '&amp;', $html);
        } else {
            $html = str_replace('&', '&amp;', $html);
        }

        $html = str_replace('<', '&lt;', $html);
        $html = str_replace('>', '&gt;', $html);
        $html = str_replace('"', '&quot;', $html);
        $html = str_replace('\'', '&#39;', $html);

        return $html;
    }

    public static function unescape($html)
    {
        $html = preg_replace_callback('/&([#\\w]+);/', function($n) {
            $n = strtolower($n);
            if ($n === 'colon') return ':';
            if ($n[0] === '#') {
                return $n[1] === 'x'
                    ? char(hexdec(substr($n, 2)))
                    : char(substr($n, 1));
            }
            return '';
        }, $html);

        return $html;
    }
}