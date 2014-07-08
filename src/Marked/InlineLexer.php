<?php

namespace Marked;

use Marked\Marked;
use Marked\Utils;
use Marked\Renderer;

class InlineLexer
{
    public $options;
    public $links;
    public $rules;
    public $renderer;
    private $inLink;

    public static function doOutput($src, $links, $options = null)
    {
        $inline = new InlineLexer($links, $options);
        return $inline->output($src);
    }

    public function __construct($links = null, $options = null)
    {
        if (!isset($links)) {
            throw new \Exception('Tokens array requires a `links` property.');
        }

        if (isset($options)) {
            $this->options = $options;
        } else {
            $this->options = Marked::$defaults;
        }
        $this->links = $links;
        $this->rules = Marked::$inline['normal'];
        if (isset($this->options['renderer'])) {
            $this->renderer = $this->options['renderer'];
        } else {
            $this->renderer = new Renderer();
        }
        $this->renderer->options = $this->options;
        $this->inLink = null;

        if ($this->options['gfm']) {
            if ($this->options['breaks']) {
                $this->rules = Marked::$inline['breaks'];
            } else {
                $this->rules = Marked::$inline['gfm'];
            }
        } else if ($this->options['pedantic']) {
            $this->rules = Marked::$inline['pedantic'];
        }
    }

    public function output($src)
    {
        $out = '';
        $link = null;
        $text = null;
        $href = null;
        $cap = null;
    
        while (strlen($src) > 0) {
            // escape
            if ($cap = $this->rules['escape']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $out .= $cap[1];
                continue;
            }
        
            // autolink
            if ($cap = $this->rules['autolink']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                if ($cap[2] === '@') {
                    $text = $cap[1][6] === ':'
                        ? $this->mangle(substr($cap[1], 7))
                        : $this->mangle($cap[1]);
                    $href = $this->mangle('mailto:').$text;
                } else {
                    $text = Utils::escape($cap[1]);
                    $href = $text;
                }
                $out .= $this->renderer->link($href, null, $text);
                continue;
            }
        
            // url (gfm)
            if (!$this->inLink && ($cap = $this->rules['url']->exec($src))) {
                $src = substr($src, strlen($cap[0]));
                $text = Utils::escape($cap[1]);
                $href = $text;
                $out .= $this->renderer->link($href, null, $text);
                continue;
            }
        
            // tag
            if ($cap = $this->rules['tag']->exec($src)) {
                if (!$this->inLink && preg_match('/^<a /i', $cap[0])) {
                    $this->inLink = true;
                } else if ($this->inLink && preg_match('/^<\\/a>/i', $cap[0])) {
                    $this->inLink = false;
                }
                $src = substr($src, strlen($cap[0]));
                $out .= $this->options['sanitize']
                    ? Utils::escape($cap[0])
                    : $cap[0];
                continue;
            }
        
            // link
            if ($cap = $this->rules['link']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $this->inLink = true;
                $out .= $this->outputLink($cap, array(
                  'href' => $cap[2],
                  'title' => isset($cap[3]) ? $cap[3] : null
                ));
                $this->inLink = false;
                continue;
            }

            // reflink, nolink
            if (($cap = $this->rules['reflink']->exec($src))
                || ($cap = $this->rules['nolink']->exec($src))) {
                $src = substr($src, strlen($cap[0]));
                $link = preg_replace('/\\s+/', ' ', (isset($cap[2]) && strlen($cap[2]) > 0 ? $cap[2] : $cap[1]));
                $_link = strtolower($link);
                if (!isset($this->links[$_link]['href'])) {
                    $out .= $cap[0][0];
                    $src = substr($cap[0], 1).$src;
                    continue;
                } else {
                    $link = $this->links[$_link];
                }
                $this->inLink = true;
                $out .= $this->outputLink($cap, $link);
                $this->inLink = false;
                continue;
            }
        
            // strong
            if ($cap = $this->rules['strong']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $out .= $this->renderer->strong($this->output(isset($cap[2]) && strlen($cap[2]) > 0 ? $cap[2] : $cap[1]));
                continue;
            }
        
            // em
            if ($cap = $this->rules['em']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $out .= $this->renderer->em($this->output(isset($cap[2]) && strlen($cap[2]) > 0 ? $cap[2] : $cap[1]));
                continue;
            }
        
            // code
            if ($cap = $this->rules['code']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $out .= $this->renderer->codespan(Utils::escape($cap[2], true));
                continue;
            }
        
            // br
            if ($cap = $this->rules['br']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $out .= $this->renderer->br();
                continue;
            }
        
            // del (gfm)
            if ($cap = $this->rules['del']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $out .= $this->renderer->del($this->output($cap[1]));
                continue;
            }

            // text
            if ($cap = $this->rules['text']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $out .= Utils::escape($this->smartypants($cap[0]));
                continue;
            }
        
            if (strlen($src) > 0) {
                throw new \Exception('Infinite loop on byte: '.strval(ord($src[0])));
            }
        }
    
        return $out;
    }

    public function outputLink($cap, $link) {
        $href = Utils::escape($link['href']);
        $title = $link['title'] ? Utils::escape($link['title']) : null;
    
        return $cap[0][0] !== '!'
            ? $this->renderer->link($href, $title, $this->output($cap[1]))
            : $this->renderer->image($href, $title, Utils::escape($cap[1]));
    }

    public function smartypants($text) {
        if (!$this->options['smartypants']) return $text;

        // em-dashes
        $text = preg_replace('/--/', '—', $text);
        // opening singles
        $text = preg_replace('/(^|[-—\\/(\\[{"\\s])\'/', '$1‘', $text);
        // closing singles & apostrophes
        $text = preg_replace('/\'/', '’', $text);
        // opening doubles
        $text = preg_replace('/(^|[-—\\/(\\[{‘\\s])"/', '$1“', $text);
        // closing doubles
        $text = preg_replace('/"/', '”', $text);
        // ellipses
        $text = preg_replace('/\\.{3}/', '…', $text);

        return $text;
    }

    public function mangle($text) {
        $out = '';
        $l = strlen($text);
        $i = 0;
        $ch = null;

        for (; $i < $l; $i++) {
            $ch = ord($text[$i]);
            if (rand(1, 10) > 5) {
                $ch = 'x' + dechex($ch);
            }
            $out .= '&#'.$ch.';';
        }
    
        return $out;
    }
}