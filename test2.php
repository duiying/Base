<?php

$key = ftok('/home/work/www/test.php', 'a');
$queue = msg_get_queue($key);

msg_receive($queue, 0, $msgType, 1024, $msg, true, MSG_NOERROR);
echo "msgType：$msgType msg：$msg" . PHP_EOL;