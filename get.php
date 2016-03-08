<?php
require_once(dirname(__FILE__)."/inc/load.php");
ini_set("max_execution_time", 100000);

if(!isset($_GET['file'])){
	die("ERR1 - no file set");
}

$FILEID = intval($_GET['file']);

if(!$FILEID){
	die("ERR2 - no file provided");
}

$res = $DB->query("SELECT * FROM files WHERE id=$FILEID");
$line = $res->fetch();

//no file found
if(!$line){
	die("ERR5 - file not found");
}

//TODO: check user rights to download here:
//if the user is logged in, he need to have the rights to
//if agent provides his voucher, check it.

$filename = dirname(__FILE__)."/files/".$line['filename'];

//file not found
if(!file_exists($filename)){
	die("ERR3 - file not present");
}

$file = $filename;
$fp = @fopen($file, "rb");

$size   = filesize($file); // File size
$length = $size;           // Content length
$start  = 0;               // Start byte
$end    = $size - 1;       // End byte

header('Content-type: '.mime_content_type($filename));
header("Accept-Ranges: bytes");
if(isset($_SERVER['HTTP_RANGE'])) {

	$c_start = $start;
	$c_end   = $end;

	list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

	if (strpos($range, ',') !== false) {
		header('HTTP/1.1 416 Requested Range Not Satisfiable');
		header("Content-Range: bytes $start-$end/$size");
		file_put_contents("/stream/log.txt", "$range-$start-$end-$size\n", FILE_APPEND);
		exit;
	}
	if($range == '-') {
		$c_start = $size - substr($range, 1);
	}
	else{
		$range  = explode('-', $range);
		$c_start = $range[0];
		if((isset($range[1]) && is_numeric($range[1]))){
			$c_end = $range[1];
		}
		else{
			$c_end = $size;
		}
	}
	if($c_end > $end){
		$c_end = $end;
	}
	if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
		header('HTTP/1.1 416 Requested Range Not Satisfiable');
		header("Content-Range: bytes $start-$end/$size");
		file_put_contents("/stream/log.txt", "$range-$start-$end-$size\n", FILE_APPEND);
		exit;
	}
	$start  = $c_start;
	$end    = $c_end;
	$length = $end - $start + 1;
	fseek($fp, $start);
	header('HTTP/1.1 206 Partial Content');
}

header("Content-Range: bytes $start-$end/$size");
header("Content-Length: ".$length);

$buffer = 1024 * 100;
while(!feof($fp) && ($p = ftell($fp)) <= $end) {

	if ($p + $buffer > $end) {
		$buffer = $end - $p + 1;
	}
	echo fread($fp, $buffer);
	flush();
}


fclose($fp);