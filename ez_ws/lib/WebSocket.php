<?php
/** 
* WebSocket class
* 
* @author      Gavin.Liu<gavinliu279535263@gmail.com> 
* @version     V1.0 
*/  

namespace Lib;

class WebSocket{
	private $ip;
	private $port;
	private $socket;
	private $resId;
	private $server;
	private $worker;

	private $closed = false;

	//websocket rcf parameter
	public $mask = false;
	public $maskingKey = '';
	
	private $isHandShake = false;

	public $log_enabled = true;

	public $eventCall = [];

	//io stream status
	const STEP_START = 1;
	const STEP_CONTINUE = 2;
	const STEP_HEAD = 3;
	const STEP_BODY = 4;

	const CALLS_NAME = ['open','message','close','pong'];

	public function setSocket($socket,$resId){
		$this->socket = $socket;
		$this->resId = $resId;

		if(socket_getpeername($this->socket,$ip,$port)){
	   		$this->ip = $ip;
	   		$this->port = $port;
	   		$this->log("client accept: ".$ip." port ".$port);
	   	}else{
	   		$this->log("get clientInfo error!");
	   	}
	}

	public function getSocket(){
		return $this->socket;
	}

	public function setWorker($worker){
		$this->worker = $worker;
	}

	
	/*function createSocket($master_ip,$master_port,$listen_backlog=20){
		static $master = null;
		$master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed");
		socket_set_option($master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
		socket_bind($master, $master_ip, $master_port)                    or die("socket_bind() failed");
		socket_listen($master,$listen_backlog)              or die("socket_listen() failed");

		$this->log("Server Started : ".date('Y-m-d H:i:s'));
		$this->log("Listening on   : ".$master_ip." port ".$master_port);
		$this->log("Master socket  : ".$master.PHP_EOL);


		($this->socket = socket_accept($master)) or die("socket_accept() failed");

		if(socket_getpeername($this->socket,$ip,$port)){
	   		$this->ip = $ip;
	   		$this->port = $port;
	   		$this->log("client accept: ".$ip." port ".$port);
	   		$this->log("client socket: ".$this->socket);
	   	}else{
	   		$this->log("get clientInfo error!");
	   	}
	}*/

	/**
	 * regist handler
	 */
	public function on($name,$func){
		$this->eventCall[$name] = $func;
	}

	/**
	 * close websocket
	 */
	public function close(){

		isset($this->eventCall['close']) && $this->eventCall['close']($this->worker->server,$this->resId);
		$this->push(WsFrame::closeFrame());
		$this->closed = true;
		@socket_close($this->socket);
		$this->log("IP:{$this->ip} port:{$this->port} closed");
	}

	/**
	 * receive message and manage it
	 */
	public function receive($data){
		if(strlen($data)>0){
	  		if(!$this->isHandShake){
	  			$this->handshake($data);
	  			$this->isHandShake = true;
	  			isset($this->eventCall['open']) && $this->eventCall['open']($this->worker->server,$this->resId);
	  		}else{
	  			$this->streamReadManage($data);
	  		}
	  	}
	}

	/**
	 * start to run the socket
	 */
	/*public function run(){
		while(true){
			if(!$this->isHandShake)
				$data = socket_read($this->socket,4096);
			else
				$data = socket_read($this->socket,5);

			if($data === false){
		  		$this->close();
		  		return;
		  	}

		  	$this->receive($data);
		}
	}*/

	/**
	 * used to send frame to client
	 */
	public function push($wf){
		if($this->closed){
			return false;
		}
		$res = @socket_write($this->socket, $wf->buf, strlen($wf->buf));
		if($res === false){
			$this->log("socket:{$this->resId} write error!");
			$this->closed = true;
			$this->worker->close($this->resId);
			return false;
		}
		return true;
	}

	/**
	 * get One emtire Frame with state machine
	 */
	private function readFrame($data,$frame_call){
		static $step = self::STEP_HEAD;
		static $frame = null;
		static $buf = null;

		switch($step){
			case self::STEP_HEAD:
					$data = $buf?$buf.$data:$data;
					$result = WsFrame::decode($data);
					if(!$result){
						if(!$buf){
							$buf  = $data;
						}else{
							$buf .= $data;
						}
					}else{
						$frame = $result['wf'];
						if(!$result['frame_end']){
		  					$step = self::STEP_BODY;
		  				}else{
		  					$frame_call && $frame_call($frame);
		  				}
					}
				break;
			case self::STEP_BODY:
					$lastLen = $frame->payload-strlen($frame->body);
					$frame_end = true;
					if ($lastLen < strLen($data)) {
						$body = substr($data,0,$lastLen);
						$data = substr($data,$lastLen);
						
					} elseif ($lastLen == strLen($data)){
						$body = $data;
						unset($data);
					}else{
						$frame_end = false;
						$body = $data;
						unset($data);
					}

					//now one entire frame has been received
					$frame->body .= $body;

					if($frame_end){
						$step = self::STEP_HEAD;
						
						$frame_call && $frame_call($frame);

						//to next frame
						if(!empty($data)){
							$this->readFrame($data,$frame_call);
						}

					}

					
				break;
		}
	}

	private function recFrameEvent($frame){
		switch($frame->opcode){
			case WsFrame::OPCODE_CLOSE_FRAME:
				//close event
				$this->worker->close($this->resId);
				break;
			case WsFrame::OPCODE_PING:
				//ping event
				$this->push(WsFrame::pongFrame());
				break;
			case WsFrame::OPCODE_PONG:
				//pong event
				isset($this->eventCall['pong']) && $this->eventCall['pong']($this->worker->server,$this->resId);
				break;
			default:
				//message event
				isset($this->eventCall['message']) && $this->eventCall['message']($this->worker->server,$this->resId,$frame);
		}
	}

	/**
	 * deal stream from socket
	 */
	private function streamReadManage($data){
		static $step = self::STEP_START;
		static $frame_call = null;
		if(!$frame_call){
			$frame_call = function($frame) use(&$step){

							//unmask body data
							if($frame->mask){
								WsFrame::bodyUnMask($frame->maskingKey,$frame->body);
							}

							if($frame->fin==WsFrame::FIN_ON){
								$step = self::STEP_START;
							}else{
								$step = self::STEP_CONTINUE;
							}

							$this->recFrameEvent($frame);
						};
		}
		

		switch($step){
			case self::STEP_START:
				$this->readFrame($data,$frame_call);
				break;
			case self::STEP_CONTINUE:	
				$this->readFrame($data,$frame_call);
				break;

		}
	}

	/**
	 * used to handshake
	 */
	function getHTMLHeaders($req){
		$r = $h = $o = $key = null;
		if (preg_match("/GET (.*) HTTP/"              ,$req,$match)) { $r = $match[1]; }
		if (preg_match("/Host: (.*)\r\n/"             ,$req,$match)) { $h = $match[1]; }
		if (preg_match("/Origin: (.*)\r\n/"           ,$req,$match)) { $o = $match[1]; }
		if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$req,$match)) { $key = $match[1]; }
		return array($r, $h, $o, $key);
	}

	/**
	 * used to handshake
	 */
	function calcKey($key){
		#规定的magic string为 258EAFA5-E914-47DA-95CA-C5AB0DC85B11
		#将“客户端”+magic string 然后进行sha1编码
		#基于websocket version 13
		$accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
		return $accept;
	}


	/**
	 * used to handShake when client is connecting
	 */
	private function handShake($data){
		list($resource, $host, $origin, $key) = $this->getHTMLHeaders($data);

		$upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
					"Upgrade: websocket\r\n" .
					"Connection: Upgrade\r\n" .
					"Sec-WebSocket-Accept: " . $this->calcKey($key) . "\r\n\r\n";  //必须以两个回车结尾

		$bytes = socket_write($this->socket, $upgrade, strlen($upgrade));

		return true;
	}

	/**
	 * used to debug
	 */
	private function log($str){
		if($this->log_enabled){
			if($this->ip && $this->port){
				$ipInfo = "IP:{$this->ip} PORT:{$this->port}";
			}else{
				$ipInfo = "";
			}
			echo "[WebSocket pid:".getmypid()." {$ipInfo} ]".$str.PHP_EOL;
		}
	}
}