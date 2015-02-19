<?php

class MarkdownTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider markdownProvider
     */
    public function testMarkdown($name, $markdown, $expected, $options)
    {
        $def = \Breezewish\Marked\Marked::$defaults;

        \Breezewish\Marked\Marked::setOptions($options);
        $html = \Breezewish\Marked\Marked::render($markdown);

        //restore default options
        \Breezewish\Marked\Marked::$defaults = $def;

        $this->assertEquals(preg_replace('/\\s/', '', $expected), preg_replace('/\\s/', '', $html), sprintf('%s failed', $name));
    }

    public function markdownProvider()
    {
        $dir = dirname(__FILE__).'/tests';
        $files = scandir($dir);

        $case_names = array();

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $info = pathinfo($file);
            $case_names[$info['filename']] = true;
        }

        $tests = array();

        foreach ($case_names as $name => $true) {
            $p = explode('.', $name);
            array_shift($p);

            $options = array();

            foreach ($p as $opt) {
                if (strpos($opt, 'no') !== false) {
                    $options[substr($opt, 2)] = false;
                } else {
                    $options[$opt] = true;
                }
            }

            $markdown = file_get_contents($dir.'/'.$name.'.text');
            $expected = file_get_contents($dir.'/'.$name.'.html');
            $tests[] = array($name, $markdown, $expected, $options);
        }

        return $tests;
    }
}