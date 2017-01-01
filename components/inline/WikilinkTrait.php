<?php
namespace jacmoe\mdpages\components\inline;
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

use yii\helpers\Url;
use yii\helpers\Html;
use JamesMoss\Flywheel\Config;
use JamesMoss\Flywheel\Repository;
use jacmoe\mdpages\helpers\Page;

/**
* Adds wikilink inline elements
*/
trait WikilinkTrait
{
    /**
     * Flywheel Config instance
     * @var \JamesMoss\Flywheel\Config
     */
    private $flywheel_config = null;

    /**
     * Flywheel Repository instance
     * @var \JamesMoss\Flywheel\Repository
     */
    private $flywheel_repo = null;

    /**
    * Parses the wikilink feature.
    * @marker [[
    */
    protected function parseWikilink($markdown)
    {
        if (preg_match('/^\[\[(.+?)\]\]/', $markdown, $matches)) {
            return [
                [
                    'wikilink',
                    $this->parseInline($matches[1])
                ],
                strlen($matches[0])
            ];
        }
        return [['text', $markdown[0] . $markdown[1]], 2];
    }

    protected function renderWikilink($block)
    {
        $link = $this->renderAbsy($block[1]);
        $has_title = strpos($link, '|');

        $url = '';
        $title = '';
        if ($has_title === false) {
            $url = $link;
        } else {
            $parts = explode('|', $link);
            $url = $parts[0];
            $title = $parts[1];
        }

        $module = \jacmoe\mdpages\Module::getInstance();

        $repo = $this->getFlywheelRepo($module);
        $page = $repo->query()->where('url', '==', $url)->execute();
        $result = $page->first();

        if($result != null) {
            return Html::a(empty($title) ? $result->title : $title, Url::to(['/' . $module->id . '/page/view', 'id' => $result->url], $module->absolute_wikilinks));
        } else {
            return '[[' . $link . ']]';
        }
    }

    /**
     *
     * @return \JamesMoss\Flywheel\Repository
     */
    private function getFlywheelRepo($module)
    {
        if(!isset($this->flywheel_config)) {
            $config_dir = \Yii::getAlias($module->flywheel_config);
            if(!file_exists($config_dir)) {
                FileHelper::createDirectory($config_dir);
            }
            $this->flywheel_config = new Config($config_dir);
        }
        if(!isset($this->flywheel_repo)) {
            $this->flywheel_repo = new Repository($module->flywheel_repo, $this->flywheel_config);
        }
        return $this->flywheel_repo;
    }

}
