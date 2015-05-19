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
	// The FrontEndFileHandler instance is stored here.
	public $fefh = null;
	
	// Path settings are stored here.
	public $cacheDir = null;
	public $urlPrefix = null;
	
	// Set this variable to true to force recompilation every time.
	public $debugMode = null;
	
	// Properties for storing CSS references.
	public $cssList = array();
	public $cssUnsetList = array();
	public $cssCacheFilename = null;
	
	// Properties for storing JS <head> references.
	public $jsHeadList = array();
	public $jsHeadUnsetList = array();
	public $jsHeadCacheFilename = null;
	
	// Properties for storing JS <body> references.
	public $jsBodyList = array();
	public $jsBodyUnsetList = array();
	public $jsBodyCacheFilename = null;
	
	// Constructor.
	public function __construct()
	{
		// Retrieve the FrontEndFileHandler instance from the Context class.
		$this->fefh = Context::getInstance()->oFrontEndFileHandler;
		
		// Create the cache directory if it does not exist.
		$this->cacheDir = _XE_PATH_ . 'files/cache/zipperupper';
		if(!file_exists($this->cacheDir))
		{
			$fileHandler = new FileHandler();
			$fileHandler->makeDir($this->cacheDir);
		}
		
		// Find out the absolute or relative path of XE installation.
		if(strncasecmp(_XE_PATH_, $_SERVER['DOCUMENT_ROOT'], strlen($_SERVER['DOCUMENT_ROOT'])) === 0)
		{
			$this->urlPrefix = substr(_XE_PATH_, strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')));
			$this->urlPrefix = str_replace('\\', '/', $this->urlPrefix);
		}
		else
		{
			$this->urlPrefix = '../../../';
		}
	}
	
	// Call this method to start zipping.
	public function zipUp()
	{
		$this->zipCSS();
		$this->zipJSHead();
		$this->zipJSBody();
	}
	
	// Merge all CSS references into one file.
	public function zipCSS()
	{
		// Get currently loaded CSS references and sort them by priority.
		$cssMap = $this->fefh->cssMap;
		ksort($cssMap);
		
		// Add all non-remote references to the list.
		foreach($cssMap as $index => $items)
		{
			foreach($items as $key => $item)
			{
				$path = $item->cdnPath . '/' . $item->fileName;
				if($item->targetIe === '' && $item->media === 'all' && !preg_match('#^(https?:)?//#i', $path))
				{
					$this->cssList[] = $this->getServerPath($path);
					$this->cssUnsetList[] = array($index, $key);
				}
			}
		}
		
		// Get the last modified timestamp and the cache file name.
		$lastModifiedTime = $this->getLastModifiedTime($this->cssList);
		$this->cssCacheFilename = $this->cacheDir . '/' . sha1(serialize($this->cssList)) . '.css';
		
		// Recompile the cache file if necessary.
		if($this->debugMode || !file_exists($this->cssCacheFilename) || filemtime($this->cssCacheFilename) <= $lastModifiedTime)
		{
			// Create a copy of $this because it cannot be passed to a closure in some versions of PHP.
			$thish = $this;
			
			// Open the cache file.
			$fp = fopen($this->cssCacheFilename, 'w');
			$canReplace = (bool)$fp;
			
			// Write the @charset directive at the top.
			fwrite($fp, '@charset "utf-8";' . "\n\n");
			
			// Open each original file.
			foreach($this->cssList as $filename)
			{
				// Trim the contents and remove duplicate @charset directives.
				$styles = trim(file_get_contents($filename));
				$styles = trim(preg_replace('#^@charset\s.+?[;\n]#i', '', $styles));
				
				// Convert all url() references to be absolute or relative to the cache file path.
				$styles = preg_replace_callback('#url\(([^\)]+)\)#i', function($matches) use($thish, $filename) {
					if(strncasecmp($matches[1], 'data:', 5) === 0)
					{
						return $matches[0];
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
				
				// Write the converted CSS to the cache file.
				fwrite($fp, '/* Source: ./' . substr($filename, strlen(_XE_PATH_)) . ' */' . "\n\n");
				fwrite($fp, $styles . "\n\n");
			}
			
			// Close the cache file.
			fclose($fp);
		}
		else
		{
			$canReplace = true;
		}
		
		// Remove original references and insert cache file instead, if it is safe to do so.
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
	
	// Merge all JS <head> references into one file.
	public function zipJSHead()
	{
		// Get currently loaded JS <head> references and sort them by priority.
		$jsHeadMap = $this->fefh->jsHeadMap;
		ksort($jsHeadMap);
		
		// Add all non-remote references to the list.
		foreach($jsHeadMap as $index => $items)
		{
			foreach($items as $key => $item)
			{
				$path = $item->cdnPath . '/' . $item->fileName;
				if(!preg_match('#^(https?:)?//#i', $path))
				{
					$this->jsHeadList[] = array($this->getServerPath($path), trim($item->targetIe));
					$this->jsHeadUnsetList[] = array($index, $key);
				}
			}
		}
		
		// Get the last modified timestamp and the cache file name.
		$lastModifiedTime = $this->getLastModifiedTime($this->jsHeadList);
		$this->jsHeadCacheFilename = $this->cacheDir . '/' . sha1(serialize($this->jsHeadList)) . '.head.js';
		
		// Recompile the cache file if necessary.
		if($this->debugMode || !file_exists($this->jsHeadCacheFilename) || filemtime($this->jsHeadCacheFilename) <= $lastModifiedTime)
		{
			// Open the cache file.
			$fp = fopen($this->jsHeadCacheFilename, 'w');
			$canReplace = (bool)$fp;
			
			// Open each original file.
			foreach($this->jsHeadList as $filename)
			{
				// Copy each original file to the cache file.
				if($fporiginal = fopen($filename[0], 'r'))
				{
					// Write a comment to indicate the source.
					fwrite($fp, '/* Source: ./' . substr($filename[0], strlen(_XE_PATH_)) . ' */' . "\n\n");
					
					// Rewrite IE conditional comments as a JS condition.
					if($filename[1] !== '' && $jsCondition = $this->parseTargetIE($filename[1]))
					{
						fwrite($fp, $jsCondition . ' {' . "\n\n");
					}
					
					// Copy the actual content of the file.
					stream_copy_to_stream($fporiginal, $fp);
					fwrite($fp, $script . "\n\n");
					
					// Close conditional comments.
					if($filename[1] !== '' && $jsCondition)
					{
						fwrite($fp, '}' . "\n\n");
					}
				}
			}
			
			// Close the cache file.
			fclose($fp);
		}
		else
		{
			$canReplace = true;
		}
		
		// Remove original references and insert cache file instead, if it is safe to do so.
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
	
	// Merge all JS <body> references into one file.
	public function zipJSBody()
	{
		// This is currently not used because it does not actually help improve page load time.
	}
	
	// Get the last modified timestamp of a set of files, including this file.
	public function getLastModifiedTime(array $filelist)
	{
		$lastModifiedTime = filemtime(__FILE__);
		foreach($filelist as $filename)
		{
			if(is_array($filename)) $filename = $filename[0];
			$lastModifiedTime = max($lastModifiedTime, filemtime($filename));
		}
		return $lastModifiedTime;
	}
	
	// Convert a path to an absolute URL, or relative to the cache file path.
	public function getClientPath($path, $relativeTo = null)
	{
		$path = str_replace('\\', '/', $this->getServerPath($path, $relativeTo));
		return $this->urlPrefix . substr($path, strlen(_XE_PATH_));
	}
	
	// Convert a path to an absolute path on the server's filesystem.
	public function getServerPath($path, $relativeTo = null)
	{
		// If the path is relative to another path, add them together.
		if($relativeTo !== null)
		{
			$path = rtrim($relativeTo, '/') . '/' . $path;
		}
		
		// If the path contains query strings or fragments, detach them.
		if(preg_match('/^(.+?)([?#].+)$/', $path, $matches))
		{
			$path = $matches[1];
			$args = $matches[2];
		}
		else
		{
			$args = '';
		}
		
		// Determine the real path of the given filename.
		$realpath = realpath(_XE_PATH_ . $path);
		if ($realpath === false)
		{
			return _XE_PATH_ . $path . $args;
		}
		else
		{
			return $realpath . $args;
		}
	}
	
	// Parse Target-IE conditional comments.
	public function parseTargetIE($target)
	{
		if(preg_match('/^(!\s?|[gl]te?\s*)?\(?IE\s*([0-9.]+)\)?$/', trim($target), $matches))
		{
			$cond = $matches[1] ? trim($matches[1]) : 'eq';
			$version = $matches[2];
			switch($cond)
			{
				case '!': $operator = '!='; break;
				case 'gt': $operator = '>'; break;
				case 'gte': $operator = '>='; break;
				case 'lt': $operator = '<'; break;
				case 'lte': $operator = '<='; break;
				default: $operator = '==';
			}
			
			return 'if (navigator.userAgent && (window.ziptargetie = navigator.userAgent.match(/MSIE ([0-9.]+)/)) && ' .
				'parseFloat(window.ziptargetie[1]) ' . $operator . ' ' . $version . ')';
		}
		else
		{
			return false;
		}
	}
}
