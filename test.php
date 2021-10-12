<?php

/**
 * 服务端
 * 读取客户端的数据并写回客户端
 */

$file = '/home/work/www/unix_udp_server_file';
if (file_exists($file)) {
    unlink($file);
}
$socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
socket_bind($socket, $file);

while (1) {
    $len = socket_recvfrom($socket,$buf, 1024, 0, $unixClientFile);
    if ($len) {
        // 读取数据
        echo sprintf('从 client 获取到了数据：%s 文件：%s' . PHP_EOL, $buf, $unixClientFile);
        // 写入数据
        socket_sendto($socket, $buf, strlen($buf), 0, $unixClientFile);
    }
    // 当收到 exit 时，退出循环
    if (trim($buf) === 'exit') {
        break;
    }
}

socket_close($socket);