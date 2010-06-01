﻿WebSocket = function(url, protocol, proxyHost, proxyPort, headers) {

     var self = this;
     var connection, iframediv,  _ID;


    this.readyState     = 3;
    this.bufferedAmount = 0;

    this.onmessage = function(e){};
    this.onopen = function(){};
    this.onclose = function(){};

    this.send = function(data) {

      var request = createRequestObject();
      if(!request)return false;
      request.onreadystatechange  = function() {};
      request.open('POST', url, true);
      if(request.setRequestHeader){
        request.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
      }
      request.send(urlEncodeData({_id: _ID, 'data': data}));
      return true;

    };

    this.close = function(){
    	if(connection){
    	this.readyState = 2;
        document.body.removeChild(connection);
        connection = false;
        this.readyState = 3;
        this.onclose();	    		
    	}
        
    };


    /*
    Кодирование данных (простого ассоциативного массива вида { name : value, ...} в
    URL-escaped строку (кодировка UTF-8)
    */
    function urlEncodeData(data) {
        var query = [];
        if (data instanceof Object) {
            for (var k in data) {
                query.push(encodeURIComponent(k) + "=" + encodeURIComponent(data[k]));
            }
            return query.join('&');
        } else {
            return encodeURIComponent(data);
        }
    };



      /*
      Создание XMLHttpRequest-объекта
      Возвращает созданный объект или null, если XMLHttpRequest не поддерживается
      */
      var createRequestObject = function() {
          var request = null;
          try {
              request = new ActiveXObject('Msxml2.XMLHTTP');
          } catch (e){}

          if(!request){
            try {
              request=new ActiveXObject('Microsoft.XMLHTTP');
            } catch (e){}
          }
          if(!request){
            try {
              request=new XMLHttpRequest();
            } catch (e){}
          }
          return request;
      };
      
      
      
      
     



     var  initialize = function() {

       this.readyState = 0;
        connection = document.createElement('iframe');
        connection.setAttribute('id',     'WebSocket_iframe');
        with (connection.style) {
          left       = top   = "-100px";
          height     = width = "1px";
          visibility = "hidden";
          position   = 'absolute';
          display    = 'none';
        }
        document.body.appendChild(connection);
         if(connection.window){
            connection.window.document.write("<html><body></body></html>");
        }else if(connection.contentWindow){
            connection.contentWindow.window.document.write("<html><body></body></html>");
        }
        

        iframediv = document.createElement('iframe');
        iframediv.setAttribute('src', url+'&_pull=1');
        iframediv.onload = function(){self.close();};
        var ws = {
           onopen : function(id){
             _ID = id;
             self.readyState = 1;
             self.onopen();
           },
           onmessage : function(data){
             var msg = {data : data};
             self.onmessage(msg);
           }
        };
        connection.contentWindow.window.document.body.appendChild(iframediv);
        if(iframediv.window){

            iframediv.window.WebSocket = ws;

        }else if(iframediv.contentWindow){

            iframediv.contentWindow.window.WebSocket = ws;

        }



    };

    initialize();
};
WebSocketServicePrivider = 'comet';