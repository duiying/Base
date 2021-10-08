<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

$pipe = '/home/work/test.pipe';

if (!file_exists($pipe)) {
    // posix_mkfifo 用于创建一个命名管道
    if (!posix_mkfifo($pipe, 0666)) {
        exit('创建命名管道失败' . PHP_EOL);
    }
}

$fd = fopen($pipe, 'w');
// 将文件设置为非阻塞方式
stream_set_blocking($fd, 0);
while (1) {
    // 接收标准输入的数据，然后写入管道
    $data = fgets(STDIN, 128);
    if ($data) {
        $len = fwrite($fd, $data, strlen($data));
        echo sprintf('写入了 %d 个字节数据' . PHP_EOL, $len);
    }
}