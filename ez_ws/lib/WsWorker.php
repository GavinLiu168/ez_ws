<?php

namespace Lib;

class WsWorker{
	private $wsSockets = [];
	private $wsList = [];
	private $wsCalls = [];
	public $server;
	private $run = true;
	private $toMainChannel;
	private $toWorkerChannel;

	function __construct(){
    }

	public function setServer(&$server){
		$this->server = $server;
	}

	public function setChannel(&$toMainChannel,&$toWorkerChannel){
		$this->toMainChannel = $toMainChannel;
		$this->toWorkerChannel = $toWorkerChannel;
	}

	public function setWsCalls($calls){
		$this->wsCalls = $calls;
	}

	public function broadcast(WsFrame $wf){

		if(!($wf instanceof WsFrame)){
			return false;
		}

		$msg = $wf->frame2String($wf);

		$iframe_msg = IPCFrame::encode(IPCFrame::CMD_BROADCAST,$msg);

		if(!$iframe_msg){
			return false;
		}

		fwrite($this->toMainChannel[0],$iframe_msg);
	}



	private function accept($socket){
		$ws = new WebSocket();
		$resId = $this->getResourceId($socket);

		$ws->setSocket($socket,$resId);
		$ws->setWorker($this);

		foreach($this->wsCalls as $x=>$y){
			$ws->on($x,$y);
		}

		$this->wsList[$resId] = $ws;
		$this->wsSockets[] = $socket;
	}

	private function receive($resId,$data){
		if(empty($this->wsList[$resId])){
			return false;
		}
		$ws = $this->wsList[$resId];
		$ws->receive($data);
	}

	public function push($resId,$wf){
		if(!isset($this->wsList[$resId]))return false;
		$ws = $this->wsList[$resId];
		return $ws->push($wf);
	}

	public function close($resId){
		if(empty($this->wsList[$resId])){
			return false;
		}

		$ws = $this->wsList[$resId];
		$index = array_search($ws->getSocket(),$this->wsSockets);

		unset($this->wsSockets[$index]);

		$ws->close();
		unset($this->wsList[$resId]);
		return true;
	}

	private function getResourceId($resource){
		$index = strpos((string)$resource, "#")+1;
		if($index===false) return false;
		return substr((string)$resource, $index);
	}


	function socket_select($wsSockets){

            $wsSockets[] = $this->server->master;

			//解决慢系统是信号捕捉时导致的EINTR
			//$res = @socket_select($wsSockets,$w,$e,NULL);
			$res = @socket_select($wsSockets,$w,$e,0,50000);

            if($e){
                $this->log("select exception:".$e->getMessage());
            }

			if($res === false){
                $this->log("select error Id:".socket_last_error()." message:".socket_strerror(socket_last_error()));
			}else{
				foreach($wsSockets as $v){
					if($v == $this->server->master){
						$client = socket_accept($this->server->master);
						if($client){
							$this->accept($client);
                            $this->log("accept cnt:".count($this->wsSockets));
						}
					}else{

						$res = socket_recv($v,  $data, 4096, 0);
						if($res === false || $res==0){
							$this->close($this->getResourceId($v));
							continue;
						}
						if($res){
							$this->receive($this->getResourceId($v),$data);
						}
					}
				}
			}
	}

	public function broadcastFrame(WsFrame $wf){

		foreach($this->wsList as $k => $v){
			$v->push($wf);
		}

	}

	private function recBroadcast(){
		if(!$this->toWorkerChannel){
			return;
		}

		$read = array($this->toWorkerChannel[1]);  
		if(@stream_select($read, $write, $e, 0)){
			 foreach($read as $channel_read){
			 	$msg = fread($channel_read,IPCFrame::MAX_SIZE);
			 	if(!$msg){
			 		continue;
			 	}
			 	$iframe = IPCFrame::decode($msg);
			 	if(!$iframe){
			 		continue;
			 	}
			 	if($iframe['cmd'] == IPCFrame::CMD_BROADCAST){
			 		$wf = WsFrame::string2Frame($iframe['msg']);
			 		if($wf){
			 			$this->broadcastFrame($wf);
			 		}
			 	}
			 }
		}
	}


    //循环要交给server才能保证信号量函数能够执行
	public function run(){
	    while($this->run){
            if(!$this->run)return;
			$this->socket_select($this->wsSockets);
			$this->recBroadcast();
		}
	}

	public function finish(){
		$this->run = false;
		foreach($this->wsSockets as $v){
			$this->close($this->getResourceId($v));
		}
	}

	public function log($str){
		echo "[WsWorker pid:".getmypid()."]".$str.PHP_EOL;
	}
}