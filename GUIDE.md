EZ_WS是easy-websocket的意思，旨在提供一个websocket的php版本引擎，让php开发人员更加快速的使用websocket。

EZ_WS使用了多进程模式以增大服务的并发能力，但windows环境下不支持php的多进程控制模块，因此windows环境下EZ_WS就会退化成单进程模式。

以下介绍下框架的基本用法：

1.框架概念

（1）WsServer类：管理整个服务的服务类，提供了面向用户的api；

（2）WsWorker类：主要的工作类，主要用户socket的管理；

（3）WebSocket类：用户描述websocket的类，是在socket上封装出来的类，主要用于处理与websocket协议相关的逻辑；

（4）WsFrame类：用于描述websocket帧的各种参数的类，也提供面向用户的生成查询frame的类；

（5）IPCFrame类：用户进程间通讯的类，主要规范了进程间通讯的协议；

（6）Task类：抽象类，用户继承实现该类则可使用异步任务；

（7）socket资源id（$resId），框架中每个socket都用框架提供的resId来统一管理；

2.引入框架


     <?php
    	require_once('../../ez_ws/ez_ws.php');
    	use Lib\WsServer;
    	use Lib\WsFrame;

3.WsServer类使用例子

    <?php

	#设置时区
    date_default_timezone_set("PRC");

    #新建server类
    $server = new WsServer("0.0.0.0",10086);

	#设置worker进程数目为5，默认为10
    $server->setWorkerNum(5);

    #注册open函数（hanshake后触发）
    $server->on("open",function(WsServer $server,$resId){
    	#ping操作
		$wf = WsFrame::pingFrame();
		$server->push($resId,$wf);
    });

    #注册close函数（closed事件触发）
    $server->on("close",function(WsServer $server,$resId,$status_Code){
    	//----to--do---
    });

	#注册pong函数（pong事件触发）
    $server->on("pong",function(WsServer $server,$resId){
    	//----to--do---
    });

	#注册message函数（收到信息时触发）
    $server->on("message",function(WsServer $server,$resId,WsFrame $wf){
    	#广播消息
		$wf->mask = 0;
		$server->broadcast($wf->encode());
    });
    
	#开启服务器
    $server->start();

4.WsFrame类api
	
	<?php
		$wf = new WsFrame();
		
		//设置是否结束帧以及是否开启掩码（注意,服务端无需开启掩码发送),可用链式操作
		$wf->finOn()->finOff()->maskOn()->maskOff();
		
		//设置操作码，不设置则默认为OPCODE_BINARY_FRAME，可链式操作
		/*OPCODE_CONTINUE=>"frame continuation",
		*OPCODE_TEXT_FRAME=>"text frame",
		*OPCODE_BINARY_FRAME=>"binary  frame",
		*OPCODE_CLOSE_FRAME=>"connection close",
		*OPCODE_PING=>"ping",
		*OPCODE_PONG=>"pong",
		*/
		$wf->setOpcode(WsFrame::OPCODE_TEXT_FRAME);

		//设置body内容,可用链式操作
		$wf->setBody("hello world");

		//将wsframe编码，发送前必须encode
		$wf->encode()；
		
		//立即生成pingframe
		$wf = WsFrame::pingFrame();
		
		//立即生成pongframe
		$wf = WsFrame::pongFrame();

		//立即生成closeFrame，status为状态码，缺省下为1001正常退出码
		$wf = WsFrame::closeFrame($status);

		//立即生成endframe，$opcode可不填，可用于大文件的分块传输
		$wf = WsFrame::endFrame($opcode);

		//获取帧的信息，以数组形式返回
		$wf->info()；

		//充值数据帧
		$wf->reset();

