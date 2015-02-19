<?php

namespace Breezewish\Marked;

use Breezewish\Marked\Marked;
use Breezewish\Marked\Renderer;
use Breezewish\Marked\InlineLexer;

class Parser
{
    public $tokens;
    public $token;
    public $options;
    public $renderer;
    private $inline;

    /**
     * Static Parse Method
     */
    public static function doParse($src, $src_links, $options)
    {
        $parser = new Parser($options);
        return $parser->parse($src, $src_links);
    }

    public function __construct($options = null)
    {
        $this->tokens = array();
        $this->token = null;
        if (isset($options)) {
            $this->options = $options;
        } else {
            $this->options = Marked::$defaults;
        }
        if (!isset($this->options['renderer'])) {
            $this->options['renderer'] = new Renderer();
        }
        $this->renderer = $this->options['renderer'];
        $this->renderer->options = $this->options;
    }

    /**
     * Parse Loop
     */
    public function parse($src, $src_links)
    {
        $this->inline = new InlineLexer($src_links, $this->options);
        $this->tokens = array_reverse($src);

        $out = '';
        while ($this->next()) {
            $out .= $this->tok();
        }

        return $out;
    }

    /**
     * Next Token
     */
    public function next()
    {
        return $this->token = array_pop($this->tokens);
    }

    /**
     * Preview Next Token
     */
    public function peek()
    {
        $l = count($this->tokens);
        if ($l == 0) {
            return null;
        } else {
            return $this->tokens[$l - 1];
        }
    }

    /**
     * Parse Text Tokens
     */
    public function parseText()
    {
        $body = $this->token['text'];

        $p = $this->peek();
        while (isset($p) && $p['type'] === 'text') {
            $n = $this->next();
            $body .= "\n" . $n['text'];
            $p = $this->peek();
        }

        return $this->inline->output($body);
    }

    /**
     * Parse Current Token
     */
    public function tok()
    {
        switch ($this->token['type']) {
            case 'space': {
                return '';
            }
            case 'hr': {
                return $this->renderer->hr();
            }
            case 'heading': {
                return $this->renderer->heading(
                    $this->inline->output($this->token['text']),
                    $this->token['depth'],
                    $this->token['text']);
            }
            case 'code': {
                return $this->renderer->code(
                    $this->token['text'],
                    isset($this->token['lang']) ? $this->token['lang'] : null,
                    isset($this->token['escaped']) ? $this->token['escaped'] : false);
            }
            case 'table': {
                $header = '';
                $body = '';
                $i = null;
                $row = null;
                $cell = null;
                $flags = null;
                $j = null;
        
                // header
                $cell = '';
                foreach ($this->token['header'] as $i => $h) {
                    $flags = array(
                        'header' => true,
                        'align' => $this->token['align'][$i]
                    );
                    $cell .= $this->renderer->tablecell(
                        $this->inline->output($h),
                        array(
                            'header' => true,
                            'align' => $this->token['align'][$i]
                        )
                    );
                }
                
                $header .= $this->renderer->tablerow($cell);

                foreach ($this->token['cells'] as $i => $row) {
                    $cell = '';

                    foreach ($row as $j => $c) {
                        $cell .= $this->renderer->tablecell(
                            $this->inline->output($c),
                            array(
                                'header' => false,
                                'align' => $this->token['align'][$j]
                            )
                        );
                    }
        
                    $body .= $this->renderer->tablerow($cell);
                }
                return $this->renderer->table($header, $body);
            }
            case 'blockquote_start': {
                $body = '';
                
                $n = $this->next();
                while ($n['type'] !== 'blockquote_end') {
                    $body .= $this->tok();
                    $n = $this->next();
                }
                
                return $this->renderer->blockquote($body);
            }
            case 'list_start': {
                $body = '';
                $ordered = $this->token['ordered'];
                
                $n = $this->next();
                while ($n['type'] !== 'list_end') {
                    $body .= $this->tok();
                    $n = $this->next();
                }
        
                return $this->renderer->lst($body, $ordered);
            }
            case 'list_item_start': {
                $body = '';
            
                $n = $this->next();
                while ($n['type'] !== 'list_item_end') {
                    $body .= $this->token['type'] === 'text'
                        ? $this->parseText()
                        : $this->tok();
                    $n = $this->next();
                }

                return $this->renderer->listitem($body);
            }
            case 'loose_item_start': {
                $body = '';
                
                $n = $this->next();
                while ($n['type'] !== 'list_item_end') {
                    $body .= $this->tok();
                    $n = $this->next();
                }
        
                return $this->renderer->listitem($body);
            }
            case 'html': {
                $html = !$this->token['pre'] && !$this->options['pedantic']
                    ? $this->inline->output($this->token['text'])
                    : $this->token['text'];
                return $this->renderer->html($html);
            }
            case 'paragraph': {
                return $this->renderer->paragraph($this->inline->output($this->token['text']));
            }
            case 'text': {
                return $this->renderer->paragraph($this->parseText());
            }
        }
    }
}