<?php

namespace Breezewish\Marked;

class Renderer
{
    public $options;

    public function __construct($options = null)
    {
        if (isset($options)) {
            $this->options = $options;
        } else {
            $this->options = array();
        }
    }

    public function code($code, $lang = null, $escaped = false)
    {
        if (isset($this->options['highlight'])) {
            $out = $this->options['highlight']($code, $lang);
            if ($out != null && $out !== $code) {
                $escaped = true;
                $code = out;
            }
        }
    
        if (!$lang) {
            return '<pre><code>'
                . ($escaped ? $code : Utils::escape($code, true))
                . "\n</code></pre>";
        }
    
        return '<pre><code class="'
            . $this->options['langPrefix']
            . Utils::escape($lang, true)
            . '">'
            . ($escaped ? $code : Utils::escape($code, true))
            . "\n</code></pre>\n";
    }

    public function blockquote($quote)
    {
        return "<blockquote>\n" . $quote . "</blockquote>\n";
    }

    public function html($html)
    {
        return $html;
    }

    public function heading($text, $level, $raw)
    {
        return '<h'
            . $level
            . ' id="'
            . $this->options['headerPrefix']
            . preg_replace('/[^\\w]+/', '-', strtolower($raw))
            . '">'
            . $text
            . '</h'
            . $level
            . ">\n";
    }
    
    public function hr()
    {
        return $this->options['xhtml'] ? "<hr/>\n" : "<hr>\n";
    }

    public function lst($body, $ordered)
    {
        $type = $ordered ? 'ol' : 'ul';
        return '<' . $type . ">\n" . $body . '</' . $type . ">\n";
    }

    public function listitem($text)
    {
        return '<li>' . $text . "</li>\n";
    }

    public function paragraph($text)
    {
        return '<p>' . $text . "</p>\n";
    }

    public function table($header, $body)
    {
        return "<table>\n"
            . "<thead>\n"
            . $header
            . "</thead>\n"
            . "<tbody>\n"
            . $body
            . "</tbody>\n"
            . "</table>\n";
    }

    public function tablerow($content)
    {
        return "<tr>\n" . $content . "</tr>\n";
    }

    public function tablecell($content, $flags)
    {
        $type = $flags['header'] ? 'th' : 'td';
        $tag = $flags['align']
            ? '<' . $type . ' style="text-align:' . $flags['align'] . '">'
            : '<' . $type . '>';
        return $tag . $content . '</' . $type . ">\n";
    }

    // span level renderer
    public function strong($text)
    {
        return '<strong>' . $text . '</strong>';
    }

    public function em($text)
    {
        return '<em>' . $text . '</em>';
    }

    public function codespan($text)
    {
        return '<code>' . $text . '</code>';
    }

    public function br()
    {
        return $this->options['xhtml'] ? '<br/>' : '<br>';
    }

    public function del($text)
    {
        return '<del>' . $text . '</del>';
    }

    public function link($href, $title, $text)
    {
        if ($this->options['sanitize']) {
            try {
                $prot = urldecode(Utils::unescape($href));
                $prot = preg_replace('/[^\\w:]/', '', $prot);
                $prot = strtolower($prot);
            } catch (\Exception $e) {
                return '';
            }
            if (strpos($prot, 'javascript:') === 0 || strpos($prot, 'vbscript:') === 0) {
                return '';
            }
        }
        $out = '<a href="' . $href . '"';
        if (strlen($title) > 0) {
          $out .= ' title="' . $title . '"';
        }
        $out .= '>' . $text . '</a>';
        return $out;
    }

    public function image($href, $title, $text)
    {
        $out = '<img src="' . $href . '" alt="' . $text . '"';
        if (strlen($title) > 0) {
            $out .= ' title="' . $title . '"';
        }
        $out .= $this->options['xhtml'] ? '/>' : '>';
        return $out;
    }
}