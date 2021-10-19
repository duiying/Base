<?php

$socket = stream_socket_client('tcp://127.0.0.1:4568');
$msg = "GET / HTTP/1.1
fdjlafjdl
fdjsaljf";
fwrite($socket, $msg, strlen($msg));
fread($socket, 8092);
