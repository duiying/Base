<?php

$client = socket_create(AF_INET, SOCK_STREAM, 0);

if (socket_connect($client, '115.159.101.225', 1234)) {
    socket_write($client, 'ping', 4);
    echo "从服务端收到了数据：" . socket_read($client, 1024) . PHP_EOL;
}

socket_close($client);