<?php

namespace Breezewish\Marked;

use Breezewish\Marked\RegExp;
use Breezewish\Marked\Utils;
use Breezewish\Marked\Renderer;
use Breezewish\Marked\Lexer;
use Breezewish\Marked\Parser;

class Marked
{
    public static $block;
    public static $inline;

    public static $defaults;

    public static function options($opt)
    {
        foreach ($opt as $k => $v) {
            self::$defaults[$k] = $v;
        }
    }

    public static function setOptions($opt)
    {
        self::options($opt);
    }

    public static function render($src, $opt = null)
    {
        if ($opt == null) {
            $opt = array_merge(array(), Marked::$defaults);
        }

        $tokens = Lexer::doLex($src, $opt);

        if (isset($opt['highlight']) && count($tokens['tokens']) > 0) {
            foreach ($tokens as $i => $token) {
                if ($token['type'] !== 'code') {
                    continue;
                }
                $ret = $opt['highlight']($token['text'], $token['lang']);
                if ($ret === null || $ret === $token['text']) {
                    continue;
                }
                $tokens[$i]['text'] = $ret;
                $tokens[$i]['escaped'] = true;
            }
        }

        $out = Parser::doParse($tokens['tokens'], $tokens['tokens_links'], $opt);
        return $out;
    }
}

/**
 * Block-Level Grammar
 */
Marked::$block = array(
    'newline'    => new RegExp('^\\n+'),
    'code'       => new RegExp('^( {4}[^\\n]+\\n*)+'),
    'fences'     => new RegExp(),
    'hr'         => new RegExp('^( *[-*_]){3,} *(?:\\n+|$)'),
    'heading'    => new RegExp('^ *(#{1,6}) *([^\\n]+?) *#* *(?:\\n+|$)'),
    'nptable'    => new RegExp(),
    'lheading'   => new RegExp('^([^\\n]+)\\n *(=|-){2,} *(?:\\n+|$)'),
    'blockquote' => new RegExp('^( *>[^\\n]+(\n(?!def)[^\\n]+)*\\n*)+'),
    'list'       => new RegExp('^( *)(bull) [\\s\\S]+?(?:hr|def|\\n{2,}(?! )(?!\\1bull )\n*|\\s*$)'),
    'html'       => new RegExp('^ *(?:comment *(?:\n|\\s*$)|closed *(?:\n{2,}|\\s*$)|closing *(?:\\n{2,}|\\s*$))'),
    'def'        => new RegExp('^ *\\[([^\\]]+)\\]: *<?([^\\s>]+)>?(?: +["(]([^\\n]+)[")])? *(?:\\n+|$)'),
    'table'      => new RegExp(),
    'paragraph'  => new RegExp('^((?:[^\\n]+\\n?(?!hr|heading|lheading|blockquote|tag|def))+)\\n*'),
    'text'       => new RegExp('^[^\\n]+')
);

Marked::$block['bullet'] = new RegExp('(?:[*+-]|\\d+\\.)');
Marked::$block['item'] = new RegExp('^( *)(bull) [^\n]*(?:\n(?!\\1bull )[^\n]*)*');
Marked::$block['item'] = Utils::replace(Marked::$block['item'], 'gm', array(
    array(new RegExp('bull', 'g'), Marked::$block['bullet']),
));

Marked::$block['list'] = Utils::replace(Marked::$block['list'], array(
    array(new RegExp('bull', 'g'), Marked::$block['bullet']),
    array('hr', '\\n+(?=\\1?(?:[-*_] *){3,}(?:\\n+|$))'),
    array('def', '\\n+(?='.Marked::$block['def']->source.')'),
));

Marked::$block['blockquote'] = Utils::replace(Marked::$block['blockquote'], array(
    array('def', Marked::$block['def']),
));

Marked::$block['_tag'] = '(?!(?:'
  .'a|em|strong|small|s|cite|q|dfn|abbr|data|time|code'
  .'|var|samp|kbd|sub|sup|i|b|u|mark|ruby|rt|rp|bdi|bdo'
  .'|span|br|wbr|ins|del|img)\\b)\\w+(?!:\\/|[^\\w\\s@]*@)\\b';

Marked::$block['html'] = Utils::replace(Marked::$block['html'], array(
    array('comment', new RegExp('<!--[\\s\\S]*?-->')),
    array('closed', new RegExp('<(tag)[\\s\\S]+?<\\/\\1>')),
    array('closing', new RegExp('<tag(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>')),
    array(new RegExp('tag', 'g'), Marked::$block['_tag']),
));

Marked::$block['paragraph'] = Utils::replace(Marked::$block['paragraph'], array(
    array('hr', Marked::$block['hr']),
    array('heading', Marked::$block['heading']),
    array('lheading', Marked::$block['lheading']),
    array('blockquote', Marked::$block['blockquote']),
    array('tag', '<'.Marked::$block['_tag']),
    array('def', Marked::$block['def']),
));

/**
 * Normal Block Grammar
 */
Marked::$block['normal'] = array_merge(array(), Marked::$block);

/**
 * GFM Block Grammar
 */
Marked::$block['gfm'] = array_merge(array(), Marked::$block['normal']);
Marked::$block['gfm']['fences'] = new RegExp('^ *(`{3,}|~{3,}) *(\\S+)? *\\n([\\s\\S]+?)\\s*\\1 *(?:\\n+|$)');
Marked::$block['gfm']['paragraph'] = new RegExp('^');
Marked::$block['gfm']['paragraph'] = Utils::replace(Marked::$block['paragraph'], array(
    array('(?!', '(?!'
        .Utils::str_replace_once('\\1', '\\2', Marked::$block['gfm']['fences']->source).'|'
        .Utils::str_replace_once('\\1', '\\3', Marked::$block['list']->source).'|')
));

/**
 * GFM + Tables Block Grammar
 */
Marked::$block['tables'] = array_merge(array(), Marked::$block['gfm']);
Marked::$block['tables']['nptable'] = new RegExp('^ *(\\S.*\\|.*)\\n *([-:]+ *\\|[-| :]*)\\n((?:.*\\|.*(?:\\n|$))*)\\n*');
Marked::$block['tables']['table'] = new RegExp('^ *\\|(.+)\\n *\\|( *[-:]+[-| :]*)\\n((?: *\\|.*(?:\\n|$))*)\\n*');

/**
 * Inline-Level Grammar
 */
Marked::$inline = array(
  'escape'   => new RegExp('^\\\\([\\\\`*{}\\[\\]()#+\\-.!_>])'),
  'autolink' => new RegExp('^<([^ >]+(@|:\\/)[^ >]+)>'),
  'url'      => new RegExp(),
  'tag'      => new RegExp('^<!--[\\s\\S]*?-->|^<\\/?\\w+(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>'),
  'link'     => new RegExp('^!?\\[(inside)\\]\\(href\\)'),
  'reflink'  => new RegExp('^!?\\[(inside)\\]\\s*\\[([^\\]]*)\\]'),
  'nolink'   => new RegExp('^!?\\[((?:\\[[^\\]]*\\]|[^\\[\\]])*)\\]'),
  'strong'   => new RegExp('^__([\\s\\S]+?)__(?!_)|^\\*\\*([\\s\\S]+?)\\*\\*(?!\\*)'),
  'em'       => new RegExp('^\\b_((?:__|[\\s\\S])+?)_\\b|^\\*((?:\\*\\*|[\\s\\S])+?)\\*(?!\\*)'),
  'code'     => new RegExp('^(`+)\\s*([\\s\\S]*?[^`])\\s*\\1(?!`)'),
  'br'       => new RegExp('^ {2,}\\n(?!\\s*$)'),
  'del'      => new RegExp(),
  'text'     => new RegExp('^[\\s\\S]+?(?=[\\\\<!\\[_*`]| {2,}\\n|$)'),
);

Marked::$inline['_inside'] = new RegExp('(?:\\[[^\\]]*\\]|[^\\[\\]]|\\](?=[^\\[]*\\]))*');
Marked::$inline['_href'] = new RegExp('\\s*<?([\\s\\S]*?)>?(?:\\s+[\'"]([\\s\\S]*?)[\'"])?\\s*');

Marked::$inline['link'] = Utils::replace(Marked::$inline['link'], array(
    array('inside', Marked::$inline['_inside']),
    array('href', Marked::$inline['_href']),
));

Marked::$inline['reflink'] = Utils::replace(Marked::$inline['reflink'], array(
    array('inside', Marked::$inline['_inside']),
));

/**
 * Normal Inline Grammar
 */
Marked::$inline['normal'] = array_merge(array(), Marked::$inline);

/**
 * Pedantic Inline Grammar
 */
Marked::$inline['pedantic'] = array_merge(array(), Marked::$inline['normal']);
Marked::$inline['pedantic']['strong'] = new RegExp('^__(?=\\S)([\\s\\S]*?\\S)__(?!_)|^\\*\\*(?=\\S)([\\s\\S]*?\\S)\\*\\*(?!\\*)');
Marked::$inline['pedantic']['em'] = new RegExp('^_(?=\\S)([\\s\\S]*?\\S)_(?!_)|^\\*(?=\\S)([\\s\\S]*?\\S)\\*(?!\\*)');

/**
 * GFM Inline Grammar
 */
Marked::$inline['gfm'] = array_merge(array(), Marked::$inline['normal']);
Marked::$inline['gfm']['escape'] = Utils::replace(Marked::$inline['escape'], array( array('])', '~|])') ));
Marked::$inline['gfm']['url'] = new RegExp('^(https?:\\/\\/[^\\s<]+[^<.,:;"\')\\]\\s])');
Marked::$inline['gfm']['del'] = new RegExp('^~~(?=\\S)([\\s\\S]*?\\S)~~');
Marked::$inline['gfm']['text'] = Utils::replace(Marked::$inline['text'], array(
    array(']|', '~]|'),
    array('|', '|https?:\\/\\/|'),
));

/**
 * GFM + Line Breaks Inline Grammar
 */
Marked::$inline['breaks'] = array_merge(array(), Marked::$inline['gfm']);
Marked::$inline['breaks']['br'] = Utils::replace(Marked::$inline['br'], array( array('{2,}', '*') ));
Marked::$inline['breaks']['text'] = Utils::replace(Marked::$inline['gfm']['text'], array( array('{2,}', '*') ));

/**
 * Options
 */
Marked::$defaults = array(
    'gfm' => true,
    'tables' => true,
    'breaks' => false,
    'pedantic' => false,
    'sanitize' => false,
    'smartLists' => false,
    'highlight' => null,
    'langPrefix' => 'lang-',
    'smartypants' => false,
    'headerPrefix' => '',
    'renderer' => new Renderer(),
    'xhtml' => false
);