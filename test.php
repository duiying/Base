<?php

echo '父进程 ID：' . posix_getpid() . PHP_EOL;

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
} else {
    echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());
    sleep(3600);
}

echo sprintf('PID：%d' . PHP_EOL, posix_getpid());