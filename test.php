<?php

$server = socket_create(AF_INET, SOCK_STREAM, 0);
socket_bind($server, '0.0.0.0', 1234);
socket_listen($server, 5);

while (1) {
    $connectSocket = socket_accept($server);
    echo "从客户端收到了数据：" . socket_read($connectSocket, 1024) . PHP_EOL;
    socket_write($connectSocket, 'pong', 4);
    sleep(2);
    socket_close($connectSocket);
}