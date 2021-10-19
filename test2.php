<?php

$socket = stream_socket_client('tcp://127.0.0.1:4568');
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
