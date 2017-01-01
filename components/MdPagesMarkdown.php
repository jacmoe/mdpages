<?php
namespace jacmoe\mdpages\components;
/*
* This file is part of
*     the yii2   _
*  _ __ ___   __| |_ __   __ _  __ _  ___  ___
* | '_ ` _ \ / _` | '_ \ / _` |/ _` |/ _ \/ __|
* | | | | | | (_| | |_) | (_| | (_| |  __/\__ \
* |_| |_| |_|\__,_| .__/ \__,_|\__, |\___||___/
*                 |_|          |___/
*                 module
*
*	Copyright (c) 2016 - 2017 Jacob Moen
*	Licensed under the MIT license
*/

use cebe\markdown\GithubMarkdown;
use DomainException;
use Highlight\Highlighter;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Inflector;
use yii\helpers\Markdown;

use jacmoe\mdpages\components\SnippetParser;

/**
* Extended Markdown parser with support for
* Snippets
* Codehighlighting
* Image tags with width and height
* TOC generation
* Headlines
*
* @author Jacob Moen <jacmoe.dk@gmail.com>
* @author Carsten Brandt <mail@cebe.cc>
*/
class MdPagesMarkdown extends GithubMarkdown
{
    use inline\WikilinkTrait;

    /**
    * @inheritDoc
    */
    protected $escapeCharacters = [
        // from Markdown
        '\\', // backslash
        '`', // backtick
        '*', // asterisk
        '_', // underscore
        '{', '}', // curly braces
        '[', ']', // square brackets
        '(', ')', // parentheses
        '#', // hash mark
        '+', // plus sign
        '-', // minus sign (hyphen)
        '.', // dot
        '!', // exclamation mark
        '<', '>',
        // added by GithubMarkdown
        ':', // colon
        '|', // pipe
        // added by MdPagesMarkdown
        '[[', ']]', // double square brackets
    ];

    /**
    * @var array translation for guide block types
    */
    public static $blockTranslations = [];

    protected $renderingContext;

    protected $headings = [];

    /**
    * @return array the headlines of this document
    * @since 2.0.5
    */
    public function getHeadings()
    {
        return $this->headings;
    }

    /**
    * @inheritDoc
    */
    protected function prepare()
    {
        parent::prepare();
        $this->headings = [];
    }

    public function parse($text)
    {
        $text_wo_frontmatter = (YII_DEBUG) ? $text : $this->removeFrontmatter($text);
        $module = \jacmoe\mdpages\Module::getInstance();
        $snippetParser = new SnippetParser;
        $snippetParser->addSnippets($module->snippets);
        $text_snipped = $snippetParser->parse($text_wo_frontmatter);
        $markup = parent::parse($text_snipped);
        if($module->generate_page_toc) {
            $markup = $this->applyToc($markup);
        }
        return $markup;
    }

    protected function removeFrontmatter($text)
    {
        $trimmedText = trim($text);
        if (strncmp('<!--', $trimmedText, 4) === 0) {
            // leading meta-block comment uses the <!-- --> style
            return substr($trimmedText, max(4, strpos($trimmedText, '-->') + 3));
        } elseif (strncmp('/*', $rawData, 2) === 0) {
            // leading meta-block comment uses the /* */ style
            return substr($trimmedText, strpos($trimmedText, '*/') + 2);
        } else {
            // no leading meta-block comment
            return $trimmedText;
        }
    }

    protected function applyToc($content)
    {
        // generate TOC
        if (!empty($this->headings) && (count($this->headings) > 1)) {
            $toc = [];
            foreach ($this->headings as $heading)
            $toc[] = '<li>' . Html::a(strip_tags($heading['title']), '#' . $heading['id']) . '</li>';
            $toc = '<div class="toc"><ol>' . implode("\n", $toc) . "</ol></div>\n";
            if (strpos($content, '</h1>') !== false)
            $content = str_replace('</h1>', "</h1>\n" . $toc, $content);
            else
            $content = $toc . $content;
        }
        return $content;
    }

    /**
    * @inheritdoc
    */
    protected function renderImage($block)
    {
        if (isset($block['refkey'])) {
            if (($ref = $this->lookupReference($block['refkey'])) !== false) {
                $block = array_merge($block, $ref);
            } else {
                return $block['orig'];
            }
        }
        $image = \Yii::getAlias('@app/web/images/') . $block['url'];
        $image_url = Url::home(true) . "images/" . $block['url'];
        if(is_file($image)) {
            $image_info = array_values(getimagesize($image));
            list($width, $height, $type, $attr) = $image_info;
        }
        return '<span class="imagewrap">'
        . '<img src="' . htmlspecialchars($image_url, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
        . (!isset($width) ? '' : ' width=' . $width)
        . (!isset($height) ? '' : ' height=' . $height)
        . ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
        . ($this->html5 ? '>' : ' />')
        . '</span>';
    }

    /**
    * @var Highlighter
    */
    private static $highlighter;

    /**
    * @inheritdoc
    */
    protected function renderCode($block)
    {
        if (self::$highlighter === null) {
            self::$highlighter = new Highlighter();
            self::$highlighter->setAutodetectLanguages([
                'apache', 'nginx',
                'bash', 'dockerfile', 'http',
                'css', 'less', 'scss',
                'javascript', 'json', 'markdown',
                'php', 'sql', 'twig', 'xml',
                ]);
        }
        try {
            if (isset($block['language'])) {
                $result = self::$highlighter->highlight($block['language'], $block['content'] . "\n");
                return "<pre><code class=\"hljs {$result->language} language-{$block['language']}\">{$result->value}</code></pre>\n";
            } else {
                $result = self::$highlighter->highlightAuto($block['content'] . "\n");
                return "<pre><code class=\"hljs {$result->language}\">{$result->value}</code></pre>\n";
            }
        } catch (DomainException $e) {
            echo $e;
            return parent::renderCode($block);
        }
    }

    /**
    * @inheritDoc
    */
    protected function renderHeadline($block)
    {
        $content = $this->renderAbsy($block['content']);
        if (preg_match('~<span id="(.*?)"></span>~', $content, $matches)) {
            $hash = $matches[1];
            $content = preg_replace('~<span id=".*?"></span>~', '', $content);
        } else {
            $hash = Inflector::slug(strip_tags($content));
        }
        $hashLink = "<a aria-label=\"Anchor link for: $hash\" href=\"#$hash\" class=\"anchor-link\"><i class=\"fa fa-link\"></i></a>";

        if ($block['level'] == 2) {
            $this->headings[] = [
                'title' => trim($content),
                'id' => $hash,
            ];
        } elseif ($block['level'] > 2) {
            if (end($this->headings)) {
                $this->headings[key($this->headings)]['sub'][] = [
                    'title' => trim($content),
                    'id' => $hash,
                ];
            }
        }

        $tag = 'h' . $block['level'];
        return "<$tag id=\"$hash\">$content $hashLink</$tag>";
    }

}
