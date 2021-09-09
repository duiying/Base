<?php

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
    sleep(60);
} else {
    while (1) {
        echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());

        $pid = pcntl_wait($status, WNOHANG | WUNTRACED);

        if ($pid > 0) {
            // 正常退出
            if (pcntl_wifexited($status)) {
                echo sprintf('子进程正常退出，status：%d' . PHP_EOL, pcntl_wexitstatus($status));
                break;
            }
            // 中断退出
            else if (pcntl_wifsignaled($status)){
                echo sprintf('子进程中断退出 1，status：%d' . PHP_EOL, pcntl_wtermsig($status));
                break;
            }
            // 一般是发送 SIGSTOP SIGTSTP 让进程停止
            else if (pcntl_wifstopped($status)) {
                echo sprintf('子进程中断退出 2，status：%d' . PHP_EOL, pcntl_wstopsig($status));
                break;
            }
        }

        sleep(3);
    }
}