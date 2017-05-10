var MusicPlayer = function(){
	var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
	var dataBuf = [];
	var pcmBuf = [];
	var isPlayStart = false;								  						
	var isDecodeStart = false;

	function auduoDecodeTask(){
		if(!isDecodeStart){
			isDecodeStart = true;
		}else{
			return;
		}

		var data = dataBuf.shift();
		if(!data){
			isDecodeStart = false;
			return;
		}
		
		audioCtx.decodeAudioData(data, function(buffer) {//解码成pcm流
						pcmBuf.push(buffer);
						playPcm();
						isDecodeStart = false;
						auduoDecodeTask();
        }, function(e) {
            alert("Fail to decode the file.");
        });
	}

	function dataCollect(arrayBuff){
		dataBuf.push(arrayBuff);
		auduoDecodeTask();
	}

	var playPcm = function(event){

		if(event){
			isPlayStart = false;
		}
		if(!isPlayStart){
			isPlayStart = true;
		}else{
			return;
		}

		var buffer = pcmBuf.shift();
   		if(!buffer){
   			isPlayStart = false;
   			return;
   		}
   		var audioBufferSouceNode = audioCtx.createBufferSource();
   		audioBufferSouceNode.onended = playPcm;
		audioBufferSouceNode.buffer = buffer;
        audioBufferSouceNode.connect(audioCtx.destination);
        audioBufferSouceNode.start(0);
	}

	this.pushData = function(data){
		var datalen = data.size;
		var reader = new FileReader();
		var audio = document.querySelector("#audio");
		reader.onload = function(evt){
			 if(evt.target.readyState == FileReader.DONE)
	         {	
	         	dataCollect(evt.target.result);
	         }
		}
		reader.readAsArrayBuffer(data);
		//reader.readAsDataURL(data);
	}
}




var DataLayer = function(ip,chat_port,music_port){

	chatSocket = new WebSocket('ws://'+ip+':'+chat_port); 
	musicSocket = new WebSocket('ws://'+ip+':'+music_port); 

	var _this = this;
	var chatSocketEnable = false;

	this.chatSend = function(msg){
		if(chatSocketEnable){
			chatSocket.send(msg);
			return true;
		}
		return false;
	}

	chatSocket.onopen = function(event) { 

		  	chatSocketEnable = true;
		  	// 监听消息
		  	chatSocket.onmessage = function(event) { 
		  		console.log('Client received a message',event); 
		  		if(_this.chatMessage && typeof _this.chatMessage == "function"){
		  			_this.chatMessage(event.data);
		  		}
		    	
		 	}; 

		  	// 监听Socket的关闭
		  	chatSocket.onclose = function(event) { 
		    	console.log('Client notified socket has closed',event);
		    	chatSocketEnable = false; 
		  	}; 

		  // 关闭Socket.... 
		  //socket.close(); 
	};

	musicSocket.onopen = function(event) { 

		  	// 监听消息
		  	musicSocket.onmessage = function(event) { 
		  		console.log('Client received a message',event); 
		  		if(_this.musicMessage && typeof _this.musicMessage == "function"){
		  			_this.musicMessage(event.data);
		  		}
		 	}; 

		  	// 监听Socket的关闭
		  	musicSocket.onclose = function(event) { 
		    	//console.log('Client notified socket has closed',event);
		    	chatSocketEnable = false; 
		  	}; 

		  // 关闭Socket.... 
		  //socket.close(); 
	};

}

function generateUUID() {
	var d = new Date().getTime();
	var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
	  var r = (d + Math.random()*16)%16 | 0;
	  d = Math.floor(d/16);
	  return (c=='x' ? r : (r&0x3|0x8)).toString(16);
	});
	return uuid;
};

function getNowFormatDate() {
    var date = new Date();
    var seperator1 = "-";
    var seperator2 = ":";
    var month = date.getMonth() + 1;
    var strDate = date.getDate();
    if (month >= 1 && month <= 9) {
        month = "0" + month;
    }
    if (strDate >= 0 && strDate <= 9) {
        strDate = "0" + strDate;
    }
    var currentdate = date.getFullYear() + seperator1 + month + seperator1 + strDate
            + " " + date.getHours() + seperator2 + date.getMinutes()
            + seperator2 + date.getSeconds();
    return currentdate;
}

var app = {
	//dl:new DataLayer("127.0.0.1",10086,10087),
	dl:new DataLayer("www.xyz100.top",10086,10087),
	id:generateUUID(),
	mp:new MusicPlayer(),
	addleftMsg:function(nickName,content){
		var html =  '<li class="odd">\
			                <a class="user" href="#"><img class="img-responsive avatar_" src="images/avatar-1.png" alt="">\
			                <span class="user-name">'+nickName+'</span></a>\
			                <div class="reply-content-box">\
			                	<span class="reply-time">'+getNowFormatDate()+'</span>\
			                    <div class="reply-content pr">\
			                    	<span class="arrow">&nbsp;</span>'
			                    	+content+
			                    '</div>\
			                </div>\
			            </li>';
		$("#main-list").append(html);
	},
	addRightMsg:function(nickName,content){
		var html =  ' <li class="even">\
			                <a class="user" href="#"><img class="img-responsive avatar_" src="images/avatar-1.png" alt="">\
			                <span class="user-name">'+nickName+'</span></a>\
			                <div class="reply-content-box">\
			                	<span class="reply-time">'+getNowFormatDate()+'</span>\
			                    <div class="reply-content pr">\
			                    	<span class="arrow">&nbsp;</span>'
			                    	+content+
			                    '</div>\
			                </div>\
			            </li>';
		$("#main-list").append(html);
	},
	addMsgLog:function(id,nickName,content){
		if(id==this.id){
			this.addRightMsg(nickName,content);
		}else{
			this.addleftMsg(nickName,content);
		}

		$(window).scrollTop($("#main-container").height());
	},
	sendEventInit:function(){
		var _this = this;
		$('#send').click(function(){
			var nickName = $("#nick_name").val();
			if(!nickName){nickName="anonymity;"}

			var content = $("#content").val();
			if(!content){
				return;
			}

			var msg = {id:_this.id,nickName:nickName,content:content};
			try{
				msg = JSON.stringify(msg)
				_this.dl.chatSend(msg);
			}catch(err){
				console.log(err);
			}
		});
	},
	receivedEventInit:function(){
		var _this = this;
		this.dl.chatMessage = function(data){
		    try{
				msg = JSON.parse(data);
				_this.addMsgLog(msg.id,msg.nickName,msg.content);
			}catch(err){
				console.log(err);
			}
		};
	},
	init:function(){
		this.sendEventInit();
		this.receivedEventInit();

		$(".close").click(function(){
        	$("#myAlert").alert('close');
    	});

		var _this = this;
    	this.dl.musicMessage = function(data){
    		_this.mp.pushData(data);
    	}
	}
};

$(function(){
	app.init();
});





