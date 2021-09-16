<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

// 忽略 SIGINT 信号
pcntl_signal(SIGINT, SIG_IGN);

$pid = pcntl_fork();

if ($pid === 0) {
    echo sprintf('子进程启动了，pid：%d' . PHP_EOL, posix_getpid());
    // 子进程已经重设信号处理程序
    pcntl_signal(SIGINT, function($signo) {
        echo sprintf('子进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
    });
}

while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();

    sleep(1);
}