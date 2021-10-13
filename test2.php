<?php

$socketFile = __DIR__ . '/' . 'master.sock';

$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

if (socket_connect($socket, $socketFile)) {
    echo 'client 写入了数据：hello' . PHP_EOL;
    socket_write($socket, 'hello', 5);
    echo 'client 收到了数据：' . socket_read($socket, 1024) . PHP_EOL;
}

socket_close($socket);