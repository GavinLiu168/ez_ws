<?php
require_once('../../ez_ws/ez_ws.php');
use Lib\WsFrame;
use Lib\Task;
use Lib\WsServer;

class MusicPushTask extends Task{

	private $server;
	private $resId;

	function __construct(WsServer $server,$resId){
		$this->server = $server; 
		$this->resId = $resId; 
	}

	protected function run(){
		$wf = new WsFrame();
		$file = fopen("test.mp3","r");
		$wf->setOpcode(WsFrame::OPCODE_BINARY_FRAME)->encode();
		while($data=fread($file,1024*200)){
			$wf->setBody($data)->encode();
			if(!$this->server->push($this->resId,$wf)){break;}
		}
		fclose($file);
	}

}