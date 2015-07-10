<?php

/**
 * @file zipperupper.addon.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license LGPL v2.1 <http://www.gnu.org/licenses/lgpl-2.1.html>
 * @brief ZipperUpper! addon
 */

if(!defined('__XE__')) exit;
if($called_position !== 'before_display_content') return;
if(version_compare(PHP_VERSION, '5.3', '<')) return;

if($addon_info->zip_target === 'ie' && preg_match('/MSIE (6|7|8|9|10)/',$_SERVER['HTTP_USER_AGENT']) === 0)
    return;

include_once 'zipperupper.class.php';
$zipperupper = new ZipperUpper();
$zipperupper->zipUp($addon_info->zip_type);
