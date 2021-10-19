# 实践 HTTP 协议，带你看透 GET 和 POST 的区别

### 前言

来看一道经典的面试题：**GET 和 POST 的区别是什么？**  

关于这个问题，有下面几个回答。  

**1、GET 传递的参数暴露在 URL 中，安全性较低，POST 通过请求体传递参数，安全性较高。**  

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





