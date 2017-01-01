<?php
namespace jacmoe\mdpages\components\yii2tech;
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
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */


/**
 * VersionControlSystem is an interface, which particular version control system implementation should match
 * in order to be used with [[SelfUpdateController]].
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
interface VersionControlSystemInterface
{
    /**
     * Checks, if there are some changes in remote repository.
     * @param string $projectRoot VCS project root directory path.
     * @param string $log if parameter passed it will be filled with related log string.
     * @return boolean whether there are changes in remote repository.
     */
    public function hasRemoteChanges($projectRoot, &$log = null);

    /**
     * Applies changes from remote repository.
     * @param string $projectRoot VCS project root directory path.
     * @param string $log if parameter passed it will be filled with related log string.
     * @return boolean whether the changes have been applied successfully.
     */
    public function applyRemoteChanges($projectRoot, &$log = null);
}
