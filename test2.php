<?php

$file = '/home/work/www/test.php';
$key = ftok($file, 'a');
$shm_id = shmop_open($key, 'c', 0666, 128);
echo shmop_read($shm_id, 0, 5) . PHP_EOL;
// 关闭共享内存段，它的原理是把共享内存段与进程的地址空间映射关系给断开，并不会删除共享内存
shmop_close($shm_id);
// 如果想删除共享内存段，需要使用 shmop_delete
shmop_delete($shm_id);