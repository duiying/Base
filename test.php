<?php

$file = '/home/work/www/suid.txt';
$uid = posix_getuid();
$euid = posix_geteuid();

echo sprintf('提权前：uid %d euid %d' . PHP_EOL, $uid, $euid);

// 提权
posix_setuid($euid);

echo sprintf('提权后：uid %d euid %d' . PHP_EOL, posix_getuid(), posix_geteuid());

if (posix_access($file,POSIX_W_OK)) {
    file_put_contents($file, 'suid');
} else {
    echo '没有写权限' . PHP_EOL;
}

// 在做完特权操作后，一定要对该进程进行降权操作以保证系统安全
posix_setuid($uid);

echo sprintf('降权后：uid %d euid %d' . PHP_EOL, posix_getuid(), posix_geteuid());

if (posix_access($file,POSIX_W_OK)) {
    file_put_contents($file, 'suid');
} else {
    echo '降权后没有写权限' . PHP_EOL;
}