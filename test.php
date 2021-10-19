<?php

$redis = new \Redis();
$redis->connect('115.159.101.225', 6397);
$redis->auth('WYX*wyx123');
echo '开始测试，pid：' . posix_getpid() . PHP_EOL;
sleep(60);
for ($i = 0; $i < 2000; $i++) {
    if ((($i + 1) % 100 === 0) && ($i > 0)) {
        echo sprintf('测试完了 %d 百次' . PHP_EOL, ($i + 1) / 100);
    }

    $redis->get('key1');
}
echo '结束测试' . PHP_EOL;
sleep(60);