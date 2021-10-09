<?php

$key = ftok(__FILE__, 'a');
$queue = msg_get_queue($key);

$pid = pcntl_fork();

if ($pid === 0) {
    while (1) {
        // MSG_IPC_NOWAIT：非阻塞，立即返回；（如果使用非阻塞方式，该函数调用的次数会非常高，所以占用 CPU 资源就会高）
        msg_receive($queue, 0, $msgType, 1024, $msg, true, MSG_IPC_NOWAIT, $error);
        if ($error != MSG_ENOMSG) {
            echo "msgType：$msgType msg：$msg" . PHP_EOL;
        }
    }
}

$i = 0;

while (1) {
    if ($i++ === 3) {
        posix_kill($pid, SIGKILL);
        break;
    }
    // 往消息队列发送一条消息
    msg_send($queue, 2, 'hello', true);
    sleep(1);
}

// 回收子进程
$pid = pcntl_wait($status);
if ($pid > 0) {
    echo "子进程 $pid 退出了" . PHP_EOL;
}

// 删除消息队列
msg_remove_queue($queue);