<?php
require_once('../../ez_ws/ez_ws.php');

use Lib\WsServer;
use Lib\WsFrame;

function dbLog($str){
	echo $str.PHP_EOL;
}

date_default_timezone_set("PRC");

$server = new WsServer("0.0.0.0",10086);
$server->setWorkerNum(5);

$server->on("open",function(WsServer $server,$resId){
	dbLog("open:".$resId);
});

$server->on("close",function(WsServer $server,$resId){
	dbLog("close:".$resId);
});
$server->on("pong",function(WsServer $server,$resId){
	dbLog("pong:".$resId);
});
$server->on("message",function(WsServer $server,$resId,WsFrame $wf){
	dbLog("message:".$resId);
	$wf->mask = 0;
	$server->broadcast($wf->encode());
});

$server->start();