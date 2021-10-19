# 实践 HTTP 协议，带你看透 GET 和 POST 的区别

**前言**  

来看一道经典的面试题：**GET 和 POST 的区别是什么？**  

关于这个问题，有下面两个回答。  

**1、GET 传递的参数暴露在 URL 中，安全性较低；POST 通过请求体传递参数，安全性较高。**  

我们先不说对错，用实践来验证。  

用 PHP 的网络编程写一个简易的 HTTP Server，如下：  

```php
<?php

$serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
// 绑定
socket_bind($serverSocket, '0.0.0.0', 9551);
// 监听
socket_listen($serverSocket, 5);
// 等待客户端连接
$connectSocket = socket_accept($serverSocket);
// 读取客户端数据
$buf = socket_read($connectSocket, 1024);

echo '收到客户端的 HTTP 请求报文数据：' . PHP_EOL;
var_dump($buf);

$htmlContent = '<html><head></head><body>hello,http</body></html>';
$httpResponse = httpResponse($htmlContent);

socket_write($connectSocket, $httpResponse, strlen($httpResponse));

// 关闭 socket
socket_close($connectSocket);
socket_close($serverSocket);

/**
 * 组装 HTTP 响应报文
 *
 * @param string $content
 * @return string
 */
function httpResponse($content = '')
{
    // 响应行
    $responseLine = "HTTP/1.1 200 OK\r\n";

    // 响应头
    $responseHeader = '';
    $responseHeader .= "Date: " . gmdate('D, d M Y H:i:s T') . "\r\n";
    $responseHeader .= "Content-Type: text/html;charset=utf-8" . "\r\n";
    // //必须 2 个 \r\n 表示头部信息结束
    $responseHeader .= "Content-Length: " . strlen($content) . "\r\n\r\n";

    // 响应体
    $responseBody = $content;

    // 组装 HTTP 响应报文（响应行、响应头、响应体）
    return $responseLine . $responseHeader . $responseBody;
}
```

然后启动这个 HTTP Server，用不同的客户端向它发起请求。  

（1）先用 Chrome 浏览器发起请求。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/浏览器发起请求.png" width="500"></div>  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/服务端收到的浏览器请求报文.png" width="1000"></div>  

可以看到，Server 端收到的 HTTP 请求报文中，参数 `param1=val1` 被放到了请求头中，那么我们可不可以将请求参数 `param1=val1` 放到 GET 请求的请求体中呢？  

（2）我们用另外的一个 HTTP 客户端工具 `curl`，来发起 GET 的 HTTP 请求。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/curl发送请求.png" width="500"></div>  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/服务端收到curl请求报文.png" width="600"></div>  

可以看到，Server 端收到的 HTTP 请求报文中，参数 `param1=val1` 被放到了请求体中。  

因此，这个说法 `1、GET 传递的参数暴露在 URL 中，安全性较低；POST 通过请求体传递参数，安全性较高。`是错误的，可能**部分浏览器在封装 HTTP 时，将 GET 传递的参数放在了请求行中，但这并不代表 GET 传递的参数是必须放在请求行中的，GET 传递的参数也可以放在请求体中**。  

关于 GET 和 POST 的安全性：HTTP 本身是明文协议，就算数据在请求体里，也是可以被记录下来的，因此如果请求要经过不信任的公网，避免泄密的**唯一手段**就是 HTTPS。（但是，在浏览器中的登录场景来看，还是不要使用 GET 提交，因为这样会在浏览器的 url 中将密码暴露）  

**2、GET 请求只能支持 ASCII 编码，而 POST 请求支持多种编码方式。**  

说法错误，比如用 `Postman` 工具：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/GET请求的编码.png" width="1000"></div>  

选择 `binary`，GET 请求也可以传输二进制格式的文件。  

**我不想用 GET 或者 POST，我想自己定义一个请求方式，怎么做？**  

`client.php`：  

```php
<?php

$socket = stream_socket_client('tcp://127.0.0.1:9551');
$msg = "DuiYing / HTTP/1.1
Host: 127.0.0.1:9551
User-Agent: curl/7.64.1
Accept: */*
Content-Length: 11
Content-Type: application/x-www-form-urlencoded

param1=val1";
fwrite($socket, $msg, strlen($msg));
$response = fread($socket, 8092);
var_dump($response);
```

执行结果：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/自定义HTTP之请求.png" width="500"></div>  
<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/自定义HTTP之响应.png" width="500"></div>  

我们把 `GET` 换成了 `DuiYing`，只是这样不太符合**规范**而已，协议都是人定的，只要客户端和服务器能彼此认同，就能**工作**。  

**总结**  

总结全文就是，GET 和 POST 没什么区别，唯一的区别就是二者的**语义不同**，从语义方面来看，GET 用于获取数据，POST 用于提交数据。  






