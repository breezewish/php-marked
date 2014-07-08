php-marked - Yet Another PHP Markdown Parser
============================================

A full-featured PHP markdown parser.

- Ported from [chjj/marked][marked]

- Support [Github Flavoured Markdown][gfm]

    - Multiple underscores in words

    - URL autolinking

    - Strikethrough

    - Fenced code blocks

    - Tables

- High performance

## Requirements

- PHP 5.3+

- Composer

## Usage

Minimal usage:

```php
echo \Marked\Marked::render('I am using __markdown__.');
// => <p>I am using <strong>markdown</strong>.</p>
```

Example setting options with default values:

```php
\Marked\Marked::setOptions(array(
    'gfm'          => true,
    'tables'       => true,
    'breaks'       => false,
    'pedantic'     => false,
    'sanitize'     => false,
    'smartLists'   => false,
    'smartypants'  => false,
    'langPrefix'   => 'lang-',
    'xhtml'        => false,
    'headerPrefix' => '',
    'highlight'    => null,
    'renderer'     => new \Marked\Renderer()
));

echo \Marked\Marked::render('I am using __markdown__.');
```

## Basic Options

### gfm

Type: `boolean`
Default: `true`

Enable [GitHub flavored markdown][gfm].

### tables

Type: `boolean`
Default: `true`

Enable GFM [tables][tables].
This option requires the `gfm` option to be true.

### breaks

Type: `boolean`
Default: `false`

Enable GFM [line breaks][breaks].
This option requires the `gfm` option to be true.

### pedantic

Type: `boolean`
Default: `false`

Conform to obscure parts of `markdown.pl` as much as possible. Don't fix any of
the original markdown bugs or poor behavior.

### sanitize

Type: `boolean`
Default: `false`

Sanitize the output. Ignore any HTML that has been input.

### smartLists

Type: `boolean`
Default: `true`

Use smarter list behavior than the original markdown. May eventually be
default with the old behavior moved into `pedantic`.

### smartypants

Type: `boolean`
Default: `false`

Use "smart" typograhic punctuation for things like quotes and dashes.

### langPrefix

Type: `string`
Default: `"lang-"`

The prefix to be append in the className of `<code>`.

### xhtml

Type: `boolean`
Default: `false`

Render XHTML.

### headerPrefix

Type: `string`
Default: `""`

The prefix to be append in the `id` attribute of headers.

## Testing

run `phpunit`

## License

The MIT License

[marked]: https://github.com/chjj/marked
[gfm]: https://help.github.com/articles/github-flavored-markdown
[tables]: https://github.com/adam-p/markdown-here/wiki/Markdown-Cheatsheet#wiki-tables
[breaks]: https://help.github.com/articles/github-flavored-markdown#newlines