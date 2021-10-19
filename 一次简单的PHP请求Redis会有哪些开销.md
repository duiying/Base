# 一次简单的 PHP 请求 Redis 会有哪些开销？

今天看到了一篇文章：[一次简单的 PHP 请求 Redis 会有哪些开销？](https://mp.weixin.qq.com/s/yl5EuQ1wEXDuIg4E98QfZA) ，在此实践一番。  

**1、测试准备**  

先往 Redis 服务中写入一个 key：  

```sh
115.159.111.225:6397> set key1 val1
OK
```

然后写一个 PHP 脚本，循环 2K 次从 Redis 中 get 出数据。  

```php
<?php

$redis = new \Redis();
$redis->connect('115.159.111.225', 6397);
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
```

**2、系统调用开销**  

使用 `strace` 命令，`-c` 参数可以统计每一系统调用的所执行的时间,次数和出错的次数等。  

```sh
# strace -c php test_redis.php
开始测试，pid：8740
测试完了 1 百次
测试完了 2 百次
测试完了 3 百次
测试完了 4 百次
测试完了 5 百次
测试完了 6 百次
测试完了 7 百次
测试完了 8 百次
测试完了 9 百次
测试完了 10 百次
测试完了 11 百次
测试完了 12 百次
测试完了 13 百次
测试完了 14 百次
测试完了 15 百次
测试完了 16 百次
测试完了 17 百次
测试完了 18 百次
测试完了 19 百次
测试完了 20 百次
结束测试
% time     seconds  usecs/call     calls    errors syscall
------ ----------- ----------- --------- --------- ----------------
 50.24    0.424111          70      6004           poll
 32.17    0.271542         135      2001           sendto
 14.93    0.126078          63      2001           recvfrom
  0.57    0.004828          32       148           mmap
  0.39    0.003266          33        98           mprotect
  0.28    0.002354          36        64         7 open
  0.26    0.002170          26        82           rt_sigaction
  0.21    0.001784          29        60           read
  0.20    0.001707          28        59           close
  0.18    0.001531          23        65           fstat
  0.17    0.001442          65        22           write
  0.12    0.001013          27        37           brk
  0.11    0.000936          46        20           munmap
  0.04    0.000339          28        12         4 lstat
  0.02    0.000169          21         8         7 stat
  0.02    0.000151          37         4           rt_sigprocmask
  0.01    0.000125          31         4           futex
  0.01    0.000117          23         5         2 access
  0.01    0.000090          90         1           nanosleep
  0.01    0.000082          27         3           getrlimit
  0.01    0.000056          18         3           getcwd
  0.01    0.000050          25         2         2 statfs
  0.00    0.000029          29         1         1 openat
  0.00    0.000028          28         1           gettid
  0.00    0.000028          28         1           clock_getres
  0.00    0.000027          27         1           lseek
  0.00    0.000027          13         2         2 ioctl
  0.00    0.000027          27         1           uname
  0.00    0.000026          26         1           arch_prctl
  0.00    0.000022          22         1           getrandom
  0.00    0.000017          17         1           set_tid_address
  0.00    0.000017          17         1           set_robust_list
  0.00    0.000000           0         1           socket
  0.00    0.000000           0         1         1 connect
  0.00    0.000000           0         2           setsockopt
  0.00    0.000000           0         1           getsockopt
  0.00    0.000000           0         1           execve
  0.00    0.000000           0         3           fcntl
------ ----------- ----------- --------- --------- ----------------
100.00    0.844189                 10723        26 total
```

我们代码所调用的 get 函数，其实是 PHP 的一个 Redis 扩展提供的。该扩展又会去调用 Linux 系统的网络库函数，库函数再去调用内核提供的系统调用。这个调用层次模型如下：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/redis系统调用.png" width="600"></div>  

**3、进程上下文切换开销**  

每次次调用 get 后，如果数据没有返回，进程都是**阻塞**掉的，因此还会导致进程进入**主动上下文切换**。  

分别赶在脚本执行开始和脚本执行完 2k 次读取 Redis 后执行如下两行命令：  

```sh
# grep ctxt /proc/8779/status
voluntary_ctxt_switches:	3
nonvoluntary_ctxt_switches:	1
# grep ctxt /proc/8779/status
voluntary_ctxt_switches:	2004
nonvoluntary_ctxt_switches:	1
```

每次 get 都会导致进程进入**自愿上下文切换**，在网络 IO 密集型的应用里**自愿上下文切换**要比**时间片到了被动切换**要多的多！  

**4、软中断开销**  

每次在 Redis 服务器返回数据的时候，网卡接收到数据包后，会通过**硬中断**的方式，通知内核有新的数据到了。这时，内核就应该调用**软中断**处理程序来响应它。  

```sh
# cat /proc/softirqs
CPU0       CPU1       CPU2       CPU3
HI:          0          0          0          0
TIMER:  196173081  145428444  154228333  163317242
NET_TX:          0          0          0          0
NET_RX:  178159928     116073      10108     160712

# cat /proc/softirqs
CPU0       CPU1       CPU2       CPU3
HI:          0          0          0          0
TIMER:  196173688  145428634  154228610  163317624
NET_TX:          0          0          0          0
NET_RX:  178170212     116073      10108     160712
```

178170212-178159928 = 10284（多出来的 284 是机器上其它的小服务）。每次 get 请求收到数据返回的时候，内核必须要支出一次软中断的开销！  

**5、总结**  

看似一次非常简单的 Redis get 操作涉及到了进程上下文切换、中断、系统调用等知识。  








