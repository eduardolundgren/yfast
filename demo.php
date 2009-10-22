<?php
require_once("settings.php");
require_once("lib/php/YFast.php");

$uri = "";

$maxAge = 1000;

$match_etag = true;

$match_modified = true;

$last_modified = true;

$expires = true;

$etags = true;

$cache = true;

$gzip = true;

extract($_GET, EXTR_IF_EXISTS);

$docRoot = SITE_PATH;

$YFast = new YFast($docRoot, array(
		"js" => "lib/js/, lib/js/build/, portlets-javascript/build/, portlets-javascript/",
		"jpg|gif|png" => "img/, lib/js/tinymce/jscripts/tiny_mce/themes/advanced/img/",
		"css" => "css/, css/build/, lib/js/ext-2.0/resources/css/"
	)
);

$YFast->loadFile($uri, array(
	match_etag => $match_etag,
	match_modified => $match_modified,
	last_modified => $last_modified,
	expires => $expires,
	etags => $etags,
	cache => $cache,
	gzip => $gzip,
	maxAge => $maxAge
));
?>