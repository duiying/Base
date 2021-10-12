<?php

// 创建一对网络套接字
$sockets = stream_socket_pair(AF_UNIX, SOCK_STREAM, 0);

$readFd     = $sockets[0];  // 读 socket
$writeFd    = $sockets[1];  // 写 socket

$pid = pcntl_fork();

// 子进程从 socket 中读取数据
if ($pid === 0) {
    while (1) {
        $data = fread($readFd, 128);
        if ($data) {
            echo sprintf('子进程收到了数据：%s' . PHP_EOL, $data);
        }
        if (trim($data) === 'exit') {
            break;
        }
    }
    exit;
}

// 父进程获取终端的输入，然后往 socket 中写入数据
while (1) {
    $data = fread(STDIN, 128);
    if ($data) {
        fwrite($writeFd, $data, strlen($data));
    }
    if (trim($data) === 'exit') {
        break;
    }
}

$pid = pcntl_wait($status);
if ($pid > 0) {
    echo "子进程 pid：$pid 退出了" . PHP_EOL;
}






