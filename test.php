<?php

function daemonize($callback)
{
    // fork 出子进程 1
    $pid = pcntl_fork();

    // 父进程退出
    if ($pid > 0) exit();

    // 创建会话，并将自己设置为组长进程和会话首进程
    $sid = posix_setsid();

    if ($sid === -1) {
        echo '会话创建失败' . PHP_EOL;
    } else {
        echo '会话创建成功，sid：' . $sid . PHP_EOL;
    }

    // 改变工作目录
    chdir('/');
    // 重设文件创建掩码
    umask(0);

    // 关掉标准输入、标准输出、标准错误文件之后，如果后面要对文件的操作时，它返回的文件描述符就从0开始，可能程序会出现错误或者警告
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);

    $stdin = fopen('/dev/null','a');    // 0
    $stdout = fopen('/dev/null','a');   // 1
    $stderr = fopen('/dev/null','a');   // 2

    echo '这里不会在终端输出了' . PHP_EOL;

    $callback();
}

$callback = function () {
    for ($i = 0; $i < 10000; $i++) {
        file_put_contents('/home/work/daemon.log',$i . PHP_EOL,FILE_APPEND | LOCK_EX);
        sleep(1);
    }
};
daemonize($callback);