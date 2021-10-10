<?php

$key = ftok(__FILE__, 'a');

$file = '/home/work/www/data.log';

$count = 0;
file_put_contents($file, $count);

// 父子进程各自读取文件内容，然后内容 +1，再写回文件，这个过程循环 1k 次
$pid = pcntl_fork();

if ($pid === 0) {
    for ($i = 0; $i < 1000; $i++) {
        $x = (int)file_get_contents($file);
        $x += 1;
        file_put_contents($file, $x);
    }

    exit();
}

for ($i = 0; $i < 1000; $i++) {
    $x = (int)file_get_contents($file);
    $x += 1;
    file_put_contents($file, $x);
}

$pid = pcntl_wait($status);
if ($pid > 0) {
    echo "子进程 pid：$pid 退出了" . PHP_EOL;
}

$count = (int)file_get_contents($file);
echo "count：$count" . PHP_EOL;
