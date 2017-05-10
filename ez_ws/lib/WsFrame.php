<?php
/** 
* WsFrame class
* 
* @author      Gavin.Liu<gavinliu279535263@gmail.com> 
* @version     V1.0 
*/  

namespace Lib;

class WsFrame{
	public $fin;
	public $opcode;
	public $mask;
	public $payload;
	public $maskingKey;
	public $body;
	public $buf;
	public $errCode;

	const FIN_OFF = 0x00;
	const FIN_ON = 0x01;
	const MASK_OFF = 0x00;
	const MASK_ON = 0x01;

	const OPCODE_CONTINUE = 0x00;
	const OPCODE_TEXT_FRAME = 0x01;
	const OPCODE_BINARY_FRAME = 0x02;
	const OPCODE_CLOSE_FRAME = 0x08;
	const OPCODE_PING = 0x09;
	const OPCODE_PONG = 0x0A;

	const OPCODE_ARRAY = [
		self::OPCODE_CONTINUE=>"frame continuation",
		self::OPCODE_TEXT_FRAME=>"text frame",
		self::OPCODE_BINARY_FRAME=>"binary  frame",
		self::OPCODE_CLOSE_FRAME=>"connection close",
		self::OPCODE_PING=>"ping",
		self::OPCODE_PONG=>"pong",
	];

	const STATUS_NORMAL_CLOSURE = 1000;
	const STATUS_GOING_AWAY = 1001;
	const STATUS_PROTOCOL_ERROR = 1002;
	const STATUS_UNSUPPORTED_DATA = 1003;
	const STATUS_NO_STATUS_RCVD = 1005;
	const STATUS_ABNORMAL_CLOSURE = 1006;
	const STATUS_INVALID_FRAME_PAYLOAD_DATA = 1007;
	const STATUS_MESSAGE_TOO_BIG = 1009;
	const STATUS_MANDATORY_EXT = 1010;
	const STATUS_INTERNAL_SERVER_ERROR = 1011;
	const STATUS_TLS_HANDSHAKE = 1015;

	const STATUS_ARRAY = [
		self::STATUS_NORMAL_CLOSURE=>"normal closure",
		self::STATUS_GOING_AWAY=>"going away",
		self::STATUS_PROTOCOL_ERROR=>"protocol error",
		self::STATUS_UNSUPPORTED_DATA=>"unsupported data",
		self::STATUS_NO_STATUS_RCVD=>"no status rcvd",
		self::STATUS_ABNORMAL_CLOSURE=>" abnormal closure",
		self::STATUS_INVALID_FRAME_PAYLOAD_DATA=>"invalid frame payload data",
		self::STATUS_MESSAGE_TOO_BIG=>"message too big",
		self::STATUS_MANDATORY_EXT=>"mandatiry ext",
		self::STATUS_INTERNAL_SERVER_ERROR=>"internal server error",
		self::STATUS_TLS_HANDSHAKE=>"tls handshake",
	];


	function __construct(){
		$this->fin = self::FIN_ON;
		$this->opcode = self::OPCODE_BINARY_FRAME;
		$this->mask = self::MASK_OFF;
	}

	/**
	 * used to show frame info
	 */
	public function info(){
		$info['fin'] = $this->fin;
		$info['opcode'] = $this->opcode;
		$info['mask'] = $this->mask;
		$info['payload'] = $this->payload;
		$info['maskingKey'] = bin2hex($this->maskingKey);
		$info['body'] = bin2hex($this->body);
		return $info;
	}

	public static function frame2String(WsFrame $wf){
		$vars = get_object_vars($wf);
		$info = [];
        foreach($vars as $k=>$v){
        	if($k == 'body' || $k == 'buf' || $k == 'maskingKey'){
        		$info[$k] = base64_encode($v); 
        	}else{
        		$info[$k] = $v;
        	}
        }

        return json_encode($info);
	}

	public static function string2Frame($str){
   		$info = json_decode($str,true);

   		if(!$info){
   			return false;
   		}	

   		$wf = new WsFrame();
   		$vars = get_object_vars($wf);
   		
   		foreach($vars as $k=>$v){
   			if(!array_key_exists($k,$info)){
   				return false;
   			}

   			if($k == 'body' || $k == 'buf'){
   				$wf->$k = base64_decode($info[$k]);
   			}else{
        		$wf->$k = $info[$k];
        	}
   		}

		return $wf;
	}

	/**
	 * used to reset attribute of frame 
	 */
	public function reset(){
		
		//remove old attribute
        $vars = get_object_vars($this);
        foreach($vars as $k=>$v){
            unset($this->$k);
        }

        //set new attrbute
        $tmp = new WsFrame();
        $vars = get_object_vars($tmp);
        foreach($vars as $k=>$v){
            $this->$k = $v;
        }
		return $this;
	}

	/**
	 * used to mask message(Abandoned,because server can not send masking message to client) 
	 */
	public static function bodyMask($maskingKey,&$data){
		for($i=0;$i<strLen($data);$i++){
			$data[$i] = $data[$i] ^ $maskingKey[$i%4];
		}
		return true;
	}


	/**
	 * unmask message from client
	 */
	public static function bodyUnMask($maskingKey,&$data){
		return self::bodyMask($maskingKey,$data);
	}


	/**
	 * get frame from buffer
	 */
	public static function decode($data){

		if(strlen($data)<=2){
			return false;
		}

		$offet = 0;

		//get base head of frame
		$head = unpack("n",substr($data,0,2))[1];
		$offet = 2;

		//get base info of head
		$fin = ($head&0x8000) >> 15;
		$opcode = ($head&0xf00) >> 8;
		$mask = ($head&0x80) >> 7;
		$payload = ($head&0x7f);
		$maskingKey = null;

		if ($payload === 126) {
			
			if(strlen($data)<$offet+2){
				return false;
			}

			//2Bytes later is payload
			$payload = unpack("n",substr($data,$offet,2))[1];
			$offet += 2;
		} elseif($payload === 127) {
			
			if(strlen($data)<$offet+8){
				return false;
			}

			//8Bytes lafter is payload
			$payload = (unpack("N",substr($data,$offet,4))[1]<<32)|(unpack("N",substr($data,$offet+4,4))[1]);
			$offet += 8;
		}

		if ($mask) {
			
			if(strlen($data)<$offet+4){
				return false;
			}

			$maskingKey = substr($data,$offet,4);
			$offet += 4;
		}

		//get body data
		$body = substr($data,$offet);

		$wf = new WsFrame();
		$wf->fin = $fin;
		$wf->opcode = $opcode;
		$wf->mask = $mask;
		$wf->payload = $payload;
		$wf->maskingKey = $maskingKey;
		$wf->body = $body;
		//$wf->buf = $data;

		return [
					'wf' => $wf,
					'frame_end' => $payload<=strlen($body),
			   ];
	}

	public function finOn(){
		$this->fin = self::FIN_ON;
		return $this;
	}

	public function finOff(){
		$this->fin = self::FIN_OFF;
		return $this;
	}

	//Abandoned,because server can not send masking message to client
	public function maskOn($maskingKey){
		$this->mask = self::MASK_ON;
		$this->maskingKey = $maskingKey;
		return $this;
	}

	//Abandoned,because server can not send masking message to client
	public function maskOff(){
		$this->fin = self::MASK_OFF;
		$this->maskingKey = null;
		return $this;
	}

	public function setOpcode($val){
		$this->opcode = $val;
		return $this;
	}

	public function setBody($body=null){
		$this->body = $body;
		return $this;
	}

	/**
	 * encode frame
	 */
	public function encode(){
		$head = ($this->fin&0x01)<<15;
		$head |= ($this->opcode&0x0f)<<8;
		$head |= ($this->mask&0x01)<<7;

		$bodyLen = strlen($this->body);


		if($bodyLen<=125){
			$head |= $bodyLen&0x7f;
			$this->payload = $bodyLen;
			$head = pack("n",$head);
		}elseif($bodyLen<65535){
			$head |= 126;
			$this->payload = 126;
			$head = pack("n",$head).pack("n",$bodyLen&0xffff);
		}else{
			$head |= 127;
			$this->payload = 127;
			$head = pack("n", $head)
					.pack("N",($bodyLen/0x100000000)&0xffffffff)
					.pack("N", $bodyLen&0xffffffff);
		}

		if($this->mask){
			$head .= pack("N",$this->maskingKey);
			$this->body && self::bodyMask($this->maskingKey,$this->body);
		}

		$this->buf = $this->body?$head.$this->body:$head;

		return $this;
		
	}

	/**
	 * get new frame used to ping
	 */
	public static function pingFrame(){
		$wf = new WsFrame();
		return $wf->finOn()->setOpcode(self::OPCODE_PING)->encode();
	}

	/**
	 * get new frame used to pong
	 */
	public static function pongFrame(){
		$wf = new WsFrame();
		return $wf->finOn()->setOpcode(self::OPCODE_PONG)->encode();
	}

	/**
	 * get new frame used to close wevsocket
	 */
	public static function closeFrame($status=self::STATUS_NORMAL_CLOSURE){
		$wf = new WsFrame();
		$wf->finOn();
		$wf->setOpcode(self::OPCODE_CLOSE_FRAME);
		$wf->setBody($status);
		return $wf->encode();
	}

	/**
	 * get new frame used to endding transfer
	 */
	public static function endFrame($opcode=null){
		$wf = new WsFrame();
		$wf->finOn();
		if(!is_null($opcode)){
			$wf->setOpcode($opcode);
		}
		return $wf->encode();	
	}
}

