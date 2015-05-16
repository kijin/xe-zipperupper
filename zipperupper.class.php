<?php

/**
 * @file zipperupper.class.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license LGPL v2.1 <http://www.gnu.org/licenses/lgpl-2.1.html>
 * @brief ZipperUpper! class
 */

if(!defined('__XE__')) exit;

class ZipperUpper
{
	public $fefh = null;
	public $cacheDir = null;
	public $relativeBasePath = null;
	public $debugMode = null;
	
	public $cssList = array();
	public $cssUnsetList = array();
	public $cssCacheFilename = null;
	
	public $jsHeadList = array();
	public $jsHeadUnsetList = array();
	public $jsHeadCacheFilename = null;
	
	public $jsBodyList = array();
	public $jsBodyUnsetList = array();
	public $jsBodyCacheFilename = null;
	
	public function __construct()
	{
		$this->fefh = Context::getInstance()->oFrontEndFileHandler;
		
		$this->cacheDir = _XE_PATH_ . 'files/cache/zipperupper';
		if(!file_exists($this->cacheDir))
		{
			$fileHandler = new FileHandler();
			$fileHandler->makeDir($this->cacheDir);
		}
		
		if(strncasecmp(_XE_PATH_, $_SERVER['DOCUMENT_ROOT'], strlen($_SERVER['DOCUMENT_ROOT'])) === 0)
		{
			$this->relativeBasePath = substr(_XE_PATH_, strlen($_SERVER['DOCUMENT_ROOT']));
		}
		else
		{
			$this->relativeBasePath = './';
		}
	}
	
	public function zipUp()
	{
		$this->zipCSS();
		$this->zipJSHead();
		$this->zipJSBody();
	}
	
	public function zipCSS()
	{
		foreach($this->fefh->cssMapIndex as $key => $index)
		{
			$item = $this->fefh->cssMap[$index][$key];
			$path = $item->cdnPath . '/' . $item->fileName;
			if(!preg_match('#^(https?:)?//#i', $path))
			{
				$this->cssList[] = $this->getServerPath($path);
				$this->cssUnsetList[] = array($index, $key);
			}
		}
		
		$lastModifiedTime = $this->getLastModifiedTime($this->cssList);
		$this->cssCacheFilename = $this->cacheDir . '/' . sha1(serialize($this->cssList)) . '.css';
		
		if($this->debugMode || !file_exists($this->cssCacheFilename) || filemtime($this->cssCacheFilename) <= $lastModifiedTime)
		{
			$thish = $this;
			$fp = fopen($this->cssCacheFilename, 'w');
			$canReplace = (bool)$fp;
			
			fwrite($fp, '@charset "utf-8";' . "\n\n");
			
			foreach($this->cssList as $filename)
			{
				$styles = trim(file_get_contents($filename));
				$styles = trim(preg_replace('#^@charset\s.+?[;\n]#i', '', $styles));
				$styles = preg_replace_callback('#url\(([^\)]+)\)#i', function($matches) use($thish, $filename) {
					if(strncasecmp($matches[1], 'data:', 5) === 0)
					{
						return $matches[1];
					}
					else
					{
						$url = trim(trim($matches[1], '\'"'));
						if($url[0] !== '/' && !preg_match('#^(https?:)?//#i', $url))
						{
							$url = $thish->getClientPath($url, substr(dirname($filename), strlen(_XE_PATH_)));
						}
						return 'url(' . $url . ')';
					}
				}, $styles);
				
				fwrite($fp, '/* Source: ./' . substr($filename, strlen(_XE_PATH_)) . ' */' . "\n\n");
				fwrite($fp, $styles . "\n\n");
			}
			
			fclose($fp);
		}
		else
		{
			$canReplace = true;
		}
		
		if($canReplace)
		{
			foreach($this->cssUnsetList as $cssUnsetItem)
			{
				unset($this->fefh->cssMap[$cssUnsetItem[0]][$cssUnsetItem[1]]);
				unset($this->fefh->cssMapIndex[$cssUnsetItem[1]]);
			}
			$this->fefh->loadFile(array('./' . substr($this->cssCacheFilename, strlen(_XE_PATH_))));
		}
	}
	
	public function zipJSHead()
	{
		$jsHeadMapIndex = $this->fefh->jsHeadMapIndex;
		asort($jsHeadMapIndex);
		
		foreach($jsHeadMapIndex as $key => $index)
		{
			$item = $this->fefh->jsHeadMap[$index][$key];
			$path = $item->cdnPath . '/' . $item->fileName;
			if(!preg_match('#^(https?:)?//#i', $path))
			{
				$this->jsHeadList[] = $this->getServerPath($path);
				$this->jsHeadUnsetList[] = array($index, $key);
			}
		}
		
		$lastModifiedTime = $this->getLastModifiedTime($this->jsHeadList);
		$this->jsHeadCacheFilename = $this->cacheDir . '/' . sha1(serialize($this->jsHeadList)) . '.head.js';
		
		if($this->debugMode || !file_exists($this->jsHeadCacheFilename) || filemtime($this->jsHeadCacheFilename) <= $lastModifiedTime)
		{
			$thish = $this;
			$fp = fopen($this->jsHeadCacheFilename, 'w');
			$canReplace = (bool)$fp;
			
			foreach($this->jsHeadList as $filename)
			{
				if($fporiginal = fopen($filename, 'r'))
				{
					fwrite($fp, '/* Source: ./' . substr($filename, strlen(_XE_PATH_)) . ' */' . "\n\n");
					stream_copy_to_stream($fporiginal, $fp);
					fwrite($fp, $script . "\n\n");
				}
			}
			
			fclose($fp);
		}
		else
		{
			$canReplace = true;
		}
		
		if($canReplace)
		{
			foreach($this->jsHeadUnsetList as $jsHeadUnsetItem)
			{
				unset($this->fefh->jsHeadMap[$jsHeadUnsetItem[0]][$jsHeadUnsetItem[1]]);
				unset($this->fefh->jsHeadMapIndex[$jsHeadUnsetItem[1]]);
			}
			$this->fefh->loadFile(array('./' . substr($this->jsHeadCacheFilename, strlen(_XE_PATH_)), 'head'));
		}
	}
	
	public function zipJSBody()
	{
		
	}
	
	public function getLastModifiedTime(array $filelist)
	{
		$lastModifiedTime = filemtime(__FILE__);
		foreach($filelist as $filename)
		{
			$lastModifiedTime = max($lastModifiedTime, filemtime($filename));
		}
		return $lastModifiedTime;
	}
	
	public function getClientPath($path, $relativeTo = null)
	{
		$path = $this->getServerPath($path, $relativeTo);
		return '../../../' . substr($path, strlen(_XE_PATH_));
	}
	
	public function getServerPath($path, $relativeTo = null)
	{
		if($relativeTo !== null)
		{
			$path = rtrim($relativeTo, '/') . '/' . $path;
		}
		
		if(preg_match('/^(.+?)([?#].+)$/', $path, $matches))
		{
			$path = $matches[1];
			$args = $matches[2];
		}
		else
		{
			$args = '';
		}
		
		return realpath(_XE_PATH_ . $path) . $args;
	}
}
