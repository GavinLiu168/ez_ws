<?php
/** 
* Task class
* 
* @author      Gavin.Liu<gavinliu279535263@gmail.com> 
* @version     V1.0 
*/  

namespace Lib;

abstract class Task{

	abstract protected function run();

	public function start(){
		$fork = false;
		if(function_exists("pcntl_fork")){
			$pid = pcntl_fork();
			if($pid>0){
				return;
			}
			$fork = true;
		}

		$this->run();

		if($fork){
			$this->finish();
			exit(0);
		}
	}

	private function finish(){
	}

}