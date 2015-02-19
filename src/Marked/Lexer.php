<?php

namespace Breezewish\Marked;

use Breezewish\Marked\Marked;

class Lexer
{
    public $tokens;
    public $tokens_links;
    public $options;
    public $rules;

    public static function doLex($src, $options)
    {
        $lexer = new Lexer($options);
        return $lexer->lex($src);
    }

    public function __construct($options = null)
    {
        $this->tokens = array();
        $this->tokens_links = array();
        if (isset($options)) {
            $this->options = $options;
        } else {
            $this->options = Marked::$defaults;
        }
        $this->rules = Marked::$block['normal'];

        if ($this->options['gfm']) {
            if ($this->options['tables']) {
                $this->rules = Marked::$block['tables'];
            } else {
                $this->rules = Marked::$block['gfm'];
            }
        }
    }

    public function lex($src)
    {
        /* TODO:
          src = src
            .replace(/\r\n|\r/g, '\n')
            .replace(/\t/g, '    ')
            .replace(/\u00a0/g, ' ')
            .replace(/\u2424/g, '\n');
        */

        $src = preg_replace('/\\r\\n|\\r/', "\n", $src);
        $src = preg_replace('/\\t/', '    ', $src);
        $src = preg_replace('/ /', ' ', $src);

        return $this->token($src, true);
    }

    public function token($src, $top, $bq = null)
    {
        $src = preg_replace('/^ +$/m', '', $src);
        $next = null;
        $loose = null;
        $cap = null;
        $bull = null;
        $b = null;
        $item = null;
        $space = null;
        $i = null;
        $l = null;

        while (strlen($src) > 0) {

            // newline
            if ($cap = $this->rules['newline']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                if (strlen($cap[0]) > 1) {
                    $this->tokens[] = array(
                        'type' => 'space'
                    );
                }
            }
        
            // code
            if ($cap = $this->rules['code']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $cap = preg_replace('/^ {4}/m', '', $cap[0]);
                $this->tokens[] = array(
                    'type' => 'code',
                    'text' => !$this->options['pedantic']
                        ? preg_replace('/\\n+$/', '', $cap, 1)
                        : $cap
                );
                continue;
            }

            // fences (gfm)
            if ($cap = $this->rules['fences']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $this->tokens[] = array(
                    'type' => 'code',
                    'lang' => $cap[2],
                    'text' => $cap[3]
                );
                continue;
            }

            // heading
            if ($cap = $this->rules['heading']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $this->tokens[] = array(
                    'type' => 'heading',
                    'depth' => strlen($cap[1]),
                    'text' => $cap[2]
                );
                continue;
            }

            // table no leading pipe (gfm)
            if ($top && ($cap = $this->rules['nptable']->exec($src))) {
                $src = substr($src, strlen($cap[0]));

                $item = array(
                    'type' => 'table',
                    'header' => preg_split('/ *\\| */', preg_replace('/^ *| *\\| *$/', '', $cap[1])),
                    'align' => preg_split('/ *\\| */', preg_replace('/^ *|\\| *$/', '', $cap[2])),
                    'cells' => explode("\n", preg_replace('/\\n$/', '', $cap[3]))
                );

                foreach ($item['align'] as $i => $align) {
                    if (preg_match('/^ *-+: *$/', $align)) {
                        $item['align'][$i] = 'right';
                    } else if (preg_match('/^ *:-+: *$/', $align)) {
                        $item['align'][$i] = 'center';
                    } else if (preg_match('/^ *:-+ *$/', $align)) {
                        $item['align'][$i] = 'left';
                    } else {
                        $item['align'][$i] = null;
                    }
                }

                foreach ($item['cells'] as $i => $cell) {
                    $item['cells'][$i] = preg_split('/ *\| */', $cell);
                }

                $this->tokens[] = $item;

                continue;
            }

            // lheading
            if ($cap = $this->rules['lheading']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $this->tokens[] = array(
                    'type' => 'heading',
                    'depth' => $cap[2] === '=' ? 1 : 2,
                    'text' => $cap[1]
                );
                continue;
            }

            // hr
            if ($cap = $this->rules['hr']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $this->tokens[] = array(
                    'type' => 'hr'
                );
                continue;
            }

            // blockquote
            if ($cap = $this->rules['blockquote']->exec($src)) {
                $src = substr($src, strlen($cap[0]));

                $this->tokens[] = array(
                    'type' => 'blockquote_start'
                );

                $cap = preg_replace('/^ *> ?/m', '', $cap[0]);

                // Pass `top` to keep the current
                // "toplevel" state. This is exactly
                // how markdown.pl works.
                $this->token($cap, $top, true);

                $this->tokens[] = array(
                    'type' => 'blockquote_end'
                );

                continue;
            }

            // list
            if ($cap = $this->rules['list']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $bull = $cap[2];

                $this->tokens[] = array(
                    'type' => 'list_start',
                    'ordered' => strlen($bull) > 1
                );

                // Get each top-level item.
                $cap = $this->rules['item']->match($cap[0]);

                $next = false;
                $l = count($cap);
                $i = 0;

                for (; $i < $l; $i++) {
                    $item = $cap[$i];

                    // Remove the list item's bullet
                    // so it is seen as the next token.
                    $space = strlen($item);
                    $item = preg_replace('/^ *([*+-]|\\d+\\.) +/', '', $item, 1);

                    // Outdent whatever the
                    // list item contains. Hacky.
                    if (strpos($item, "\n ") !== false) {
                        $space -= strlen($item);
                        $item = !$this->options['pedantic']
                            ? preg_replace('/^ {1,'.$space.'}'.'/m', '', $item)
                            : preg_replace('/^ {1,4}/m', '', $item);
                    }

                    // Determine whether the next list item belongs here.
                    // Backpedal if it does not belong in this list.
                    if ($this->options['smartLists'] && $i !== $l - 1) {
                        $b = Marked::$block['bullet']->exec($cap[$i + 1]);
                        $b = $b[0];
                        if ($bull !== $b && !(strlen($bull) > 1 && strlen($b) > 1)) {
                            $src = implode("\n", array_slice($cap, $i + 1)).$src;
                            $i = $l - 1;
                        }
                    }

                    // Determine whether item is loose or not.
                    // Use: /(^|\n)(?! )[^\n]+\n\n(?!\s*$)/
                    // for discount behavior.
                    $loose = $next || preg_match('/\\n\\n(?!\\s*$)/', $item);
                    if ($i !== $l - 1) {
                        $next = $item[strlen($item) - 1] === "\n";
                        if (!$loose) $loose = $next;
                    }

                    $this->tokens[] = array(
                        'type' => $loose
                            ? 'loose_item_start'
                            : 'list_item_start'
                    );

                    // Recurse.
                    $this->token($item, false, $bq);

                    $this->tokens[] = array(
                        'type' => 'list_item_end'
                    );
                }

                $this->tokens[] = array(
                    'type' => 'list_end'
                );

                continue;
            }

            // html
            if ($cap = $this->rules['html']->exec($src)) {
                $src = substr($src, strlen($cap[0]));
                $this->tokens[] = array(
                    'type' => $this->options['sanitize']
                        ? 'paragraph'
                        : 'html',
                    'pre' => isset($cap[1]) ? ($cap[1] === 'pre' || $cap[1] === 'script' || $cap[1] === 'style') : false,
                    'text' => $cap[0]
                );
                continue;
            }

            // def
            if ((!$bq && $top) && ($cap = $this->rules['def']->exec($src))) {
                $src = substr($src, strlen($cap[0]));
                $this->tokens_links[strtolower($cap[1])] = array(
                    'href' => $cap[2],
                    'title' => isset($cap[3]) ? $cap[3] : null
                );
                continue;
            }

            // table (gfm)
            if ($top && ($cap = $this->rules['table']->exec($src))) {
                $src = substr($src, strlen($cap[0]));

                $item = array(
                    'type' => 'table',
                    'header' => preg_split('/ *\\| */', preg_replace('/^ *| *\\| *$/', '', $cap[1])),
                    'align' => preg_split('/ *\\| */', preg_replace('/^ *|\\| *$/', '', $cap[2])),
                    'cells' => explode("\n", preg_replace('/(?: *\\| *)?\\n$/', '', $cap[3]))
                );

                foreach ($item['align'] as $i => $align) {
                    if (preg_match('/^ *-+: *$/', $align)) {
                        $item['align'][$i] = 'right';
                    } else if (preg_match('/^ *:-+: *$/', $align)) {
                        $item['align'][$i] = 'center';
                    } else if (preg_match('/^ *:-+ *$/', $align)) {
                        $item['align'][$i] = 'left';
                    } else {
                        $item['align'][$i] = null;
                    }
                }

                foreach ($item['cells'] as $i => $cell) {
                    $item['cells'][$i] = preg_split('/ *\\| */', preg_replace('/^ *\\| *| *\\| *$/', '', $cell));
                }
                
                $this->tokens[] = $item;

                continue;
            }

            // top-level paragraph
            if ($top && ($cap = $this->rules['paragraph']->exec($src))) {
                $src = substr($src, strlen($cap[0]));
                $this->tokens[] = array(
                    'type' => 'paragraph',
                    'text' => $cap[1][strlen($cap[1]) - 1] === "\n"
                        ? substr($cap[1], 0, -1)
                        : $cap[1]
                );
                continue;
            }

            // text
            if ($cap = $this->rules['text']->exec($src)) {
                // Top-level should never reach here.
                $src = substr($src, strlen($cap[0]));
                $this->tokens[] = array(
                    'type' => 'text',
                    'text' => $cap[0]
                );
                continue;
            }

            if (strlen($src) > 0) {
                throw new \Exception('Infinite loop on byte: '.strval(ord($src[0])));
            }
        }

        return array(
            'tokens' => $this->tokens,
            'tokens_links' => $this->tokens_links
        );
    }
}