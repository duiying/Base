<?php

$file = '/home/work/www/test.php';
$key = ftok($file, 'a');
$shm_id = shmop_open($key, 'c', 0666, 128);
shmop_write($shm_id, 'hello', 0);
