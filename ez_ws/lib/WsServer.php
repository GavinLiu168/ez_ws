<?php
/**
* WsServer class
*
* @author      Gavin.Liu<gavinliu279535263@gmail.com>
* @version     V1.0
*/
namespace Lib;

class WsServer{
	public $master;
	private $ip;
	private $port;

    public $wwPids = [];
    public $daemonPid; //主进程pid，用于区分主进程和子进程
	public $worker;
	private $wsCalls = [];

	private $pcntlSupport = false;
	public $mutiProcessEnable = true;
	private $workerNum = 10; //the number of worker process

	private $toMainChannel; //used to rpc
	private $toWorkerChannel = []; //used to rpc

	function __construct($ip,$port){
		$this->masterSocketInit($ip,$port);
	}

    /**
     * used to set the number of worker process
     */
    public function setWorkerNum($num){
        if( getType($num)!=="integer" || $num<=0){
            return false;
        }

        $this->workerNum = $num;
        return true;
    }

    /**
     * used to enable mutiProcess or disable mutiProcess
     */
    public function setMutiProcessEnable($enable = true){
        if(gettype($enable)!=="boolean"){
            return false;
        }
        $this->mutiProcessEnable = $enable;
    }

    /**
     * used for multiprocess
     */
    private function pcntlInit(){
        if(!$this->mutiProcessEnable){
            return ;
        }
        if(function_exists("pcntl_fork")){
            $this->pcntlSupport = true;
        }else{
            $this->log("warning: your environment does not support pcntl!");
            return;
        }

        $this->daemonPid = getmypid();

        declare(ticks = 1);
        $server = $this;
        $signal_end_handler = function ($signal) use($server){
                //call child process to exit
                if(getmypid()==$server->daemonPid && $server->wwPids){
                    foreach($server->wwPids as $x){
                        $server->log("send SIGTERM to:".$x);
                        posix_kill($x, SIGTERM);
                    }
                }
                $server->finish();
                $server->log("process exit");
                exit;
        };

        pcntl_signal(SIGTERM, $signal_end_handler);
        //pcntl_signal(SIGINT, $signal_end_handler);
        pcntl_signal(SIGQUIT, $signal_end_handler);

        //auto recycle child process
        pcntl_signal(SIGCLD, SIG_IGN);

        //toMainchannel init
        $this->toMainChannel = $this->createChannel(true);
    }

    private function createChannel($block = false){

        $channel = stream_socket_pair(STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP);
        if(!$block){
            stream_set_blocking($channel[0], 0);
            stream_set_blocking($channel[1], 0);
        }

        return $channel;
    }

    /**
     * server broadcast message to client
     */
    public function broadcast(WsFrame $wf){
        if(!$this->mutiProcessEnable || !$this->pcntlSupport){
            //single process pattern
            $this->worker->broadcastFrame($wf);
            return false;
        }
        return $this->worker->broadcast($wf);
    }



    private function masterSocketInit($ip,$port){
        $this->ip = $ip;
        $this->port = $port;
        $this->master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)     or die("socket_create() failed");
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
        socket_bind($this->master, $ip, $port)                    or die("socket_bind() failed");
        socket_listen($this->master,20)                                or die("socket_listen() failed");

        $this->log("Server Started : ".date('Y-m-d H:i:s'));
        $this->log("Listening on   : ".$ip." port ".$port);
        $this->log("Master socket  : ".$this->master.PHP_EOL);
    }

    /**
     * register handler
     */
    public function on($name,$func){
        if(in_array($name,WebSocket::CALLS_NAME) && is_object($func) && ($func instanceof \Closure)){
            $this->wsCalls[$name] = $func;
        }
    }

    /**
     * push client with frame
     * parameter:$resId=>resource id
     */
    public function push($resId,$wf){
        if(empty($this->worker)){
            $this->log("private worker is not init");
            return false;
        }
        return $this->worker->push($resId,$wf);
    }

    /**
     * close websocket
     * parameter:$resId=>resource id
     */
    public function close($resId){
        $this->worker->close($resId);
    }

    public function createWorker(){
        $ww = new WsWorker();
        $ww->setServer($this);
        $ww->setWsCalls($this->wsCalls);
        $ww->setChannel($this->toMainChannel,$this->toWorkerChannel[count($this->toWorkerChannel)-1]);

        $this->worker = $ww;
        $ww->run();

    }

    private function recWorkerMsg(){
        $read = array($this->toMainChannel[1]);
        if(@stream_select($read, $write, $e, NULL)){
            foreach($read as $channel_read){
                $msg = fread($channel_read,IPCFrame::MAX_SIZE);
                if(!$msg){
                    continue;
                }
                $iframe = IPCFrame::decode($msg);
                if(!$iframe){
                    continue;
                }

                switch($iframe['cmd']){
                    case IPCFrame::CMD_BROADCAST:
                        foreach($this->toWorkerChannel as $v){
                            fwrite($v[0],$msg);
                        }
                        break;

                }
            }
        }
    }

    /**
     * start running server
     */
    public function start(){
        $this->pcntlInit();
        if($this->mutiProcessEnable && $this->pcntlSupport){
            for($i=0;$i<$this->workerNum;$i++){
                $this->toWorkerChannel[] = $this->createChannel();
                $pid = pcntl_fork();
                if($pid===-1){
                    $this->log('fork error');
                }else if(!$pid){
                    //子进程
                    $this->createWorker();
                    return;
                }
                $this->wwPids[] = $pid;
            }

            //------------daemon process---------------------
            fclose($this->toMainChannel[0]);
            foreach($this->toWorkerChannel as $v){
                fclose($v[1]);
            }

            while(true){
                $this->recWorkerMsg();
            }

        }else{
            //single process
            $this->createWorker();
        }

    }

    public function finish(){
        if($this->worker){
            $this->worker->finish();
        }
    }

    public function log($str){
        echo "[WsServer pid:".getmypid()."]".$str.PHP_EOL;
    }
}

