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