<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

function printId()
{
    $pid = posix_getpid();
    // sid：会话 ID；pgid：进程组长 ID；
    echo sprintf('pid=%d ppid=%d pgid=%d sid=%d' . PHP_EOL, posix_getpid(), posix_getppid(), posix_getpgrp(), posix_getsid($pid));
}

$pid = pcntl_fork();

printId();

// 父进程退出
if ($pid > 0) exit();

// 创建会话，并将自己设置为组长进程和会话首进程
$sid = posix_setsid();

if ($sid === -1) {
    echo '会话创建失败' . PHP_EOL;
} else {
    echo '会话创建成功，sid：' . $sid . PHP_EOL;
}

printId();

while (1) {
    ;
}