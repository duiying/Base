<?php

// ftok：Convert a pathname and a project identifier to a System V IPC key（把一个路径和标识符转换成 IPC 的 key）
$key = ftok(__FILE__, 'a');
// msg_get_queue：Create or attach to a message queue（根据传入的键创建或返回一个消息队列的引用）
$queue = msg_get_queue($key);

// 往消息队列发送一条消息
msg_send($queue, 2, 'hello');

echo '消息队列发送成功' . PHP_EOL;