<?php
/** 
* IPCFrame class
* 
* @author      Gavin.Liu<gavinliu279535263@gmail.com> 
* @version     V1.0 
*/  

namespace Lib;

class IPCFrame{
	const CMD_BROADCAST = 1;

	const MAX_SIZE = 40960; // 40K BYTE

	function __construct(){

	}


	/**
	 * encode msg 
	 * int  $cmd
	 * string $msg
	 */
	public static function encode($cmd,$msg){
		$frame['cmd'] = $cmd;
		$frame['msg'] = $msg;
		$ret = json_encode($frame);
		if(!$ret || strlen($ret)>self::MAX_SIZE){
			return false;
		}
		return $ret;
	}

	/**
	 * decode msg
	 */
	public static function decode($buf){
		return json_decode($buf,true);
	}

}