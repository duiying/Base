<?php

/**
 * 客户端
 * 启动了两个进程：一个父进程，一个子进程
 * 父进程读取终端输入，并发送给服务端
 * 子进程读取服务端返回并打印
 */

$file = '/home/work/www/unix_udp_client_file';
$serverFile = '/home/work/www/unix_udp_server_file';
if (file_exists($file)) {
    unlink($file);
}
$socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
socket_bind($socket, $file);

$pid = pcntl_fork();

// 子进程读取数据
if ($pid === 0) {
    while (1) {
        $len = socket_recvfrom($socket, $buf, 1024, 0, $unixClientFile);
        if ($len) {
            echo sprintf('从 server 获取到了数据：%s 文件：%s' . PHP_EOL, $buf, $unixClientFile);
        }
        // 当收到 exit 时，退出循环
        if (trim($buf) === 'exit') {
            break;
        }

    }
    exit;
}

// 父进程写入数据
while (1) {
    $data = fread(STDIN, 128);
    if ($data) {
        socket_sendto($socket, $data, strlen($data), 0, $serverFile);
    }
    // 当收到 exit 时，退出循环
    if (trim($data) === 'exit') {
        break;
    }
}

$pid = pcntl_wait($status);
if ($pid > 0) {
    echo "子进程 pid：$pid 退出了" . PHP_EOL;
}
socket_close($socket);