<?php
require_once('../../ez_ws/ez_ws.php');
require_once('MusicPushTask.php');

use Lib\WsServer;
use Lib\WsFrame;

function dbLog($str){
	echo $str.PHP_EOL;
}

date_default_timezone_set("PRC");

$server = new WsServer("0.0.0.0",10087);
$server->setWorkerNum(5);

$server->on("open",function(WsServer $server,$resId){
	dbLog("open:".$resId);

	$mpt = new MusicPushTask($server,$resId);
	$mpt->start();
});

$server->on("close",function(WsServer $server,$resId,$status_Code){
    dbLog("close:".$resId." status_Code:".$status_Code);
});

$server->on("pong",function(WsServer $server,$resId){
	dbLog("pong:".$resId);
});
$server->on("message",function(WsServer $server,$resId,WsFrame $wf){
	dbLog("message:".$resId);
});

$server->start();