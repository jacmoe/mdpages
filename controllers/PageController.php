<?php
namespace jacmoe\mdpages\controllers;
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

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use JamesMoss\Flywheel\Config;
use JamesMoss\Flywheel\Repository;
use jacmoe\mdpages\helpers\Page;
use jacmoe\mdpages\components\feed\Feed;
use jacmoe\mdpages\components\feed\Item;
use jacmoe\mdpages\components\MdPagesMarkdown;

/**
 * Default controller for the `mdpages` module
 */
class PageController extends Controller
{
    /**
     * @var \jacmoe\mdpages\Module
     */
    public $module;

    /**
     * Flywheel Config instance
     * @var \JamesMoss\Flywheel\Config
     */
    protected $flywheel_config = null;

    /**
     * Flywheel Repository instance
     * @var \JamesMoss\Flywheel\Repository
     */
    protected $flywheel_repo = null;

    public $defaultAction = 'view';

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Renders a page
     * @param string $id id of page (url)
     * @return string
     */
    public function actionView($id = 'index')
    {
        $dir = Yii::getAlias('@pages');
        if(!file_exists($dir)) {
            return $this->render('empty');
        }

        $repo = $this->getFlywheelRepo();
        $page = $repo->query()->where('url', '==', $id)->execute();
        $result = $page->first();

        if($result != null) {

            $this->buildBreadcrumbs($result->url);

            $view_params = array_slice((array)$result, 2);
            foreach($view_params as $key => $value) {
                Yii::$app->view->params[$key] = $value;
            }
            $github_link = 'https://github.com/'
                . $this->module->github_owner
                . '/' . $this->module->github_repo
                . '/blob'
                . '/' . $this->module->github_branch
                . $result->file;
            Yii::$app->view->params['github-link'] = $github_link;

            //TODO: either turn this into an option
            // or remove it altogether
            //$this->setMetatags($result);

            //TODO: this should probably go as there is
            // little need for diferent layouts
            // if(isset($result->layout)) {
            //     //TODO: error checking ?
            //     $this->layout = $result->layout;
            // }

            $cacheKey = 'content-' . $id;
            $content = $this->module->cache->get($cacheKey);
            $headings = $this->module->cache->get($cacheKey . '-headings');
            if ((!$content) || (!$headings)) {
                $parser = new MdPagesMarkdown();

                $page_content = Yii::getAlias('@pages') . $result->file;
                if(!file_exists($page_content)) {
                    throw new NotFoundHttpException("Cound not find the page to render.");
                }
                $content = $parser->parse(file_get_contents($page_content));
                $headings = $parser->getHeadings();

                $this->module->cache->set($cacheKey, $content, $this->module->caching_time);
                $this->module->cache->set($cacheKey . '-headings', $headings, $this->module->caching_time);
            }

            if(isset($result->view)) {
                $view_to_use = $result->view;
                if(isset(Yii::$app->view->theme)) {
                    $render_view = Yii::$app->view->theme->getBasePath() . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'page' . DIRECTORY_SEPARATOR . $result->view . '.' . Yii::$app->view->defaultExtension;
                } else {
                    $render_view = 'nothing';
                }
                if(!is_file($render_view)) {
                    $render_view = $this->getViewPath() . DIRECTORY_SEPARATOR . $result->view . '.' . Yii::$app->view->defaultExtension;
                    if(!is_file($render_view)) {
                        throw new NotFoundHttpException("Cound not find the view called '$result->view'.");
                    }
                }
                return $this->render($view_to_use, array('content' => $content, 'headings' => $headings, 'page' => $result));
            } else {
                return $this->render('view', array('content' => $content, 'headings' => $headings, 'page' => $result));
            }

        } else {
            throw new NotFoundHttpException("Cound not find the page to render.");
        }
    }

    public function actionRss()
    {
        $repo = $this->getFlywheelRepo();

        $posts = null;
        if($this->module->feed_filtering) {
            list($field, $operator, $value) = $this->module->feed_filter;
            $posts = $repo->query()->where($field, $operator, $value)->orderBy($this->module->feed_ordering)->limit(50,0)->execute();
        } else {
            $posts = $repo->query()->orderBy($this->module->feed_ordering)->limit(50,0)->execute();
        }

        $feed = new Feed();
        $feed->title = $this->module->feed_title;
        $feed->link = Url::to(['page/rss'], true);
        $feed->selfLink = Url::to(['page/rss'], true);
        $feed->description = $this->module->feed_description;
        $feed->language = 'en';
        //$feed->setWebMaster('user@email.com', 'John Doe');
        //$feed->setManagingEditor('user@email.com', 'John Doe');
        foreach ($posts as $post) {
            $item = new Item();
            $item->title = $post->title;
            $item->link = Url::to(['page/view', 'id' => $post->url], true);
            $item->guid = Url::to(['page/view', 'id' => $post->url], true);
            $item->description = $post->description;
            $item->pubDate = $post->updated;
            $item->setAuthor($this->module->feed_author_email, $this->module->feed_author_name);
            $feed->addItem($item);
        }
        $feed->render();

    }

    /**
     *
     * @return \JamesMoss\Flywheel\Repository
     */
    public function getFlywheelRepo()
    {
        if(!isset($this->flywheel_config)) {
            $config_dir = Yii::getAlias($this->module->flywheel_config);
            if(!file_exists($config_dir)) {
                FileHelper::createDirectory($config_dir);
            }
            $this->flywheel_config = new Config($config_dir);
        }
        if(!isset($this->flywheel_repo)) {
            $this->flywheel_repo = new Repository($this->module->flywheel_repo, $this->flywheel_config);
        }
        return $this->flywheel_repo;
    }

    /**
     * [buildBreadcrumbs description]
     * @param  [type] $file_url [description]
     * @return [type]           [description]
     */
    private function buildBreadcrumbs($file_url) {
        $cacheKey = 'breadcrumbs-' . $file_url;
        $breadcrumbs = $this->module->cache->get($cacheKey);
        if (!$breadcrumbs) {
            $page_parts = explode('/', $file_url);

            $repo = $this->getFlywheelRepo();

            $breadcrumbs = array();

            $i = 0;
            $out = '';
            $crumbs = array();
            foreach($page_parts as $part) {
                $out = $out . '/' . $page_parts[$i];
                $crumbs[] = substr($out, 1);
                $i++;
            }

            if($file_url != 'index') {
                Yii::$app->view->params['breadcrumbs'][] = array('label' => Page::title('index'), 'url' => Url::to(array('page/view', 'id' => 'index')));
            }

            foreach($crumbs as $crumb) {
                $page = $repo->query()->where('url', '==', $crumb)->execute();
                $result = $page->first();
                if($result != null) {
                    if($result->url == $crumbs[count($page_parts)-1]) {
                        Yii::$app->view->params['breadcrumbs'][] = array('label' => $result->title);
                    } else {
                        Yii::$app->view->params['breadcrumbs'][] = array('label' => $result->title, 'url' => Url::to(array('page/view', 'id' => $result->url)));
                    }
                } else {
                    Yii::$app->view->params['breadcrumbs'][] = array('label' => $crumb, 'class' => 'disabled');
                }
            }
            $this->module->cache->set($cacheKey, $breadcrumbs, $this->module->caching_time);
        }

        return $breadcrumbs;
    }

    /**
     * [setMetatags description]
     * @param [type] $page [description]
     */
    private function setMetatags($page) {
        if(isset($page->description)) {
            Yii::$app->view->registerMetaTag([
                'name' => 'description',
                'content' => $page->description
            ]);
        }
        if(isset($page->keywords)) {
            Yii::$app->view->registerMetaTag([
                'name' => 'keywords',
                'content' => $page->keywords
            ]);
        }
    }

}
