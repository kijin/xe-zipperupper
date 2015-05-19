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

include_once 'zipperupper.class.php';
$zipperupper = new ZipperUpper();
$zipperupper->zipUp($addon_info->zip_type);
