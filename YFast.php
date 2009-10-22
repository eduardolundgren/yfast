<?php
/*
 * YFast - makes your site faster and get A on YSlow!
 *
 * Copyright (c) 2007 Eduardo Lundgren (eduardolundgren.com)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * $Date: 2007-31-10 00:53:06 -0300 (Wed, 10 Oct 2007) $
 * $Rev: 2 $ 
 */

class YFast {
	
	private $docRoot;
	
	private $uri;
	
	private $uriRealPath;
	
	private $gzFilePath;
	
	private $uriDir;
	
	private $fileName;

	private $extension;

	private $mimeType;
	
	private $lastModified;
	
	private $expires;
	
	private $maxAge;
	
	private $etag;
	
	private $cacheControl;
	
	private $typePaths = array();
	
	private $knownTypes = array(
	    "htm"  => "text/html",
	    "html" => "text/html",
	    "js"   => "text/javascript",
	    "css"  => "text/css",
	    "xml"  => "text/xml",
	    "gif"  => "image/gif",
	    "jpg"  => "image/jpeg",
	    "jpeg" => "image/jpeg",
	    "png"  => "image/png",
	    "txt"  => "text/plain"
	);
	
	public function __construct($docRoot, $paths = array(), $types = array()) {
		
		$this->docRoot = $this->normalizePath($docRoot);
		
		$this->typePaths = array_merge($this->typePaths, $paths);
		
		$this->knownTypes = array_merge($this->knownTypes, $types);
		
	}
	
	public function noCache() {
		header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
	}
	
	public function autoCache($config = array()) {
	
		$config = array_merge(array(
			match_etag => true,
			match_modified => true,
			last_modified => true,
			expires => true,
			etags => true,
			cache => true,
			gzip => false,
			maxAge => 1000
		), $config);

		$id = md5($html);
		
		$this->lastModified = getlastmod();
		
		$this->maxAge = $config["maxAge"] * 24 * 60 * 60;
		
		$this->expires = $this->lastModified + $this->maxAge;
		
		$this->etag = dechex($this->lastModified);
		
		$this->cacheControl = "must-revalidate, proxy-revalidate, max-age=".
			$this->maxAge . ", s-maxage=" . $this->maxAge;
		
		if ($config["last_modified"])
			$this->setLastModifiedHeader();
		
		if ($config["expires"])
			$this->setExpiresHeader();
		
		if ($config["etags"])
			$this->setETagsHeader();
		
		if ($config["cache"])
			$this->setCacheControlHeader();
		
		
	   if ($config["match_modified"] || $config["match_etag"])
			$this->getIfModified();
		
		echo $html;
		
	}
	
	public function loadFile($uri, $config = array()) {
	
		$config = array_merge(array(
			match_etag => true,
			match_modified => true,
			last_modified => true,
			expires => true,
			etags => true,
			cache => true,
			gzip => true,
			maxAge => 1000
		), $config);
		
		$this->uri = $uri;
		
		$this->uriRealPath = $this->getRealPath($uri);
		
		$this->fileName = basename($this->uriRealPath);
		
		$this->checkPermission($uri);
		
		$this->checkExistence($uri);
		
		$this->uriDir = str_replace($this->fileName, "", $this->uriRealPath);
				
		$this->extension = $this->getExtension($this->fileName);

		$this->mimeType = $this->getMimeType($this->extension);
		
		$this->lastModified = filemtime($this->uriRealPath);
		
		$this->maxAge = $config["maxAge"] * 24 * 60 * 60;
		
		$this->expires = $this->lastModified + $this->maxAge;
		
		$this->etag = dechex($this->lastModified);
		
		$this->cacheControl = "must-revalidate, proxy-revalidate, max-age=".
			$this->maxAge . ", s-maxage=" . $this->maxAge;
		

		if ($config["gzip"])
			$this->obStart();
		
		if ($config["last_modified"])
			$this->setLastModifiedHeader();
		
		if ($config["expires"])
			$this->setExpiresHeader();
		
		if ($config["etags"])
			$this->setETagsHeader();
		
		if ($config["cache"])
			$this->setCacheControlHeader();

		
		if ($config["gzip"]) {
			
			$this->setGzipHeader();
			
			$this->gzFilePath = $this->uriDir."/gz/".$this->fileName.".gz";
			
			
			if (file_exists($this->gzFilePath)) {
		        $srcLastModified = filemtime( $this->uriRealPath );
		        $gzLastModified = filemtime( $this->gzFilePath );
	
		        if ($srcLastModified > $gzLastModified)
		       	// we need to recreate it...
		            @unlink($this->gzFilePath);
		    }
		    
		    if (!file_exists($this->gzFilePath)) {
		    	// create gzip version
				@mkdir($this->uriDir."/gz/", 0777);
				$this->gzCompressFile($this->uriRealPath, $this->gzFilePath, 9);
		    }
		    
		    $this->uriRealPath = $this->gzFilePath;
		    
		}
		
		
	   if ($config["match_modified"] || $config["match_etag"])
			$this->getIfModified();

		$this->setContentTypeHeader();
		
		
		readgzfile($this->uriRealPath);
		
		if ($config["gzip"])
			$this->obEndFlush();
	}
	
	public function obStart() {
		ob_start('ob_gzhandler');
	}
	
	public function obEndFlush() {
		ob_end_flush();
	}
	
	public function setLastModifiedHeader() {
		header("Last-Modified: " . date( "r", $this->lastModified ));
	}
	
	public function setExpiresHeader() {
		header("Expires: " . date("r", $this->expires));
	}
	
	public function setETagsHeader() {
		header("ETag: " . $this->etag);
	}
	
	public function setCacheControlHeader() {
		header("Cache-Control: " . $this->cacheControl);
	}

	public function setGzipHeader() {
		header("Content-Encoding: gzip");
	}
	
	public function setContentLengthHeader($bytes) {
		header("Content-Length: $bytes");
	}
	
	public function getIfModified() {
		if ($this->httpMatchETag() || $this->httpMatchModified()) {
	    	header( "HTTP/1.1 304 Not Modified" );
	        exit;
	    }
	}
	
	public function setContentTypeHeader() {
		header("Content-Type: ".$this->mimeType);
	}
	
	public function httpMatchETag() {
		global $_SERVER;
		return ereg("^".$this->etag."|[*]$", $_SERVER["HTTP_IF_NONE_MATCH"]);
	}
	
	public function httpMatchModified() {
		global $_SERVER;
		$lastModHeader = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
		$tLastModHeader = strtotime($lastModified ? $lastModified : time());
		return $this->lastModified < $tLastModHeader ? true : false;
	}
	
	public function checkExistence($uri) {
		if (!file_exists($this->uriRealPath)) {
			header("HTTP/1.1 404 Not Found");
    		echo("<h1>HTTP 404 - Not Found</h1>");
			exit;
		}
	}
	
	public function checkPermission($uri) {
		if (strpos($this->uriRealPath, $this->docRoot) !== 0) {
			header("HTTP/1.1 403 Forbidden");
			echo("<h1>HTTP 403 - Forbidden</h1>");
			exit;
		}
	}
	
	public function getRealPath($filename) {
		if (ereg("^(\.{1,2}[/\])+", $filename) && realpath($filename))
			return $this->normalizePath(realpath($filename));
		
		foreach ($this->typePaths as $exts => $path) {
			if (eregi("\.$exts$", $filename)) {
				
				$path = explode(",", $path);
				
				foreach ($path as $pth) {
					$pth = $this->docRoot . trim($pth) . $filename;
					
					if (file_exists($pth))
						return $pth;
				}
				
				return $this->normalizePath($this->docRoot . $path . $filename);
			}
		}
		return null;		
	}
	
	private function getExtension($filename) {
		eregi("\.([^.]+)*$", basename($filename), $m);
		return $m[1];
	}
	
	private function getMimeType($extension) {
		return $this->knownTypes[$extension];
	}
	
	private function normalizePath($p) {
		return ereg_replace("[/\]", "/", $p);
	}
	
	function gzCompressFile($source, $dest, $level = false){
		$mode = "wb" . $level;
		if ($fp_out = gzopen($dest, $mode)) {
			if ($fp_in = fopen($source, "rb")) {
				while (!feof($fp_in)) {
					gzwrite($fp_out, fread($fp_in,1024*512));
				}
				fclose($fp_in);
			}
			gzclose($fp_out);
		}
		return $dest;
	}

	public function __destruct() { /*TODO*/ }
}

?>