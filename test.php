<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

pcntl_signal(SIGCHLD, function($signo) {
    echo sprintf('进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
    $pid = pcntl_waitpid(-1, $status, WNOHANG | WUNTRACED);
    if ($pid > 0) {
        echo sprintf('我是父进程，我捕捉到子进程 pid：%d 退出了' . PHP_EOL, $pid, $signo);
    }
});

$pid = pcntl_fork();

// 父进程
if ($pid > 0) {
    while (1){
        // 必须要有该方法
        pcntl_signal_dispatch();
        echo sprintf('父进程 pid：%d 正在执行' . PHP_EOL, posix_getpid());
        sleep(1);

    }
}
// 子进程
else {
    echo sprintf('子进程 pid：%d 即将退出' . PHP_EOL, posix_getpid());
    exit(10);
}