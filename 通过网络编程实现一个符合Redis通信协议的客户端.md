# 通过网络编程实现一个符合 Redis 通信协议的客户端

### 前言

本文的目标旨在不借助语言提供的 Redis 操作 API 下，通过语言提供的网络编程，实现一个最基础的 Redis 客户端，要求支持 GET、SET 操作。    

比如我现在使用的 PHP 语言没有安装 Redis 扩展，那我怎么和 Redis 服务进行通信呢？此时就需要通过 Socket 编程，根据 Redis 通信协议规定的格式，实现和 Redis Server 的数据交互。  

### Redis 通信协议

**Redis 通信协议需要先了解一下**（下面内容拷贝自：[通信协议](http://redisdoc.com/topic/protocol.html) ）：  

客户端和服务器通过 TCP 连接来进行数据交互， 服务器默认的端口号为 6379 。  

客户端和服务器发送的命令或数据一律以 `\r\n`（CRLF）结尾。  

以下是这个协议的一般形式：  

```
*<参数数量> CR LF
$<参数 1 的字节数量> CR LF
<参数 1 的数据> CR LF
...
$<参数 N 的字节数量> CR LF
<参数 N 的数据> CR LF
```

举个例子， 以下是一个命令协议的打印版本：  

```
*3
$3
SET
$5
mykey
$7
myvalue
```

这个命令的实际协议值如下：  

```
*3\r\n$3\r\nSET\r\n$5\r\nmykey\r\n$7\r\nmyvalue\r\n
```

### 实现

首先启动一个在后台运行的 Redis Server：  

```sh
# /home/work/service/redis/redis.conf 作了以下修改：
bind 0.0.0.0  # 允许所有 IP 都可以访问 Redis Server
daemonize yes # 后台运行

# 如何其它机器还不能连接该 Redis Server，可能需要关闭防火墙
systemctl stop firewalld.service

# 启动
[root@bogon src]# ./redis-server /home/work/service/redis/redis.conf

# 查看进程
[root@bogon src]# ps -ef | grep redis
root      21675      1  0 22:42 ?        00:00:04 ./redis-server 0.0.0.0:6379
root      22180  19905  0 23:26 pts/1    00:00:00 grep --color=auto redis

# 查看 Redis Server 进程打开的 fd
[root@bogon src]# ll /proc/21675/fd
总用量 0
lrwx------ 1 root root 64 8月  17 22:42 0 -> /dev/null
lrwx------ 1 root root 64 8月  17 22:42 1 -> /dev/null
lrwx------ 1 root root 64 8月  17 22:42 2 -> /dev/null
lr-x------ 1 root root 64 8月  17 22:42 3 -> pipe:[260776]
l-wx------ 1 root root 64 8月  17 22:42 4 -> pipe:[260776]
lrwx------ 1 root root 64 8月  17 22:42 5 -> anon_inode:[eventpoll]
lrwx------ 1 root root 64 8月  17 22:42 6 -> socket:[261202]
```

我们先写入一个 key：  

```sh
[work@bogon src]$ /home/work/lib/redis-5.0.5/src/redis-cli -h 127.0.0.1 -p 6379
127.0.0.1:6379> set key1 val1
OK
```

此时，再次查看 Redis Server 进程打开的 fd，由于刚才的连接，发现多了一个 7 的 fd。  

```sh
[root@bogon src]# ll /proc/21675/fd
总用量 0
lrwx------ 1 root root 64 8月  17 22:42 0 -> /dev/null
lrwx------ 1 root root 64 8月  17 22:42 1 -> /dev/null
lrwx------ 1 root root 64 8月  17 22:42 2 -> /dev/null
lr-x------ 1 root root 64 8月  17 22:42 3 -> pipe:[260776]
l-wx------ 1 root root 64 8月  17 22:42 4 -> pipe:[260776]
lrwx------ 1 root root 64 8月  17 22:42 5 -> anon_inode:[eventpoll]
lrwx------ 1 root root 64 8月  17 22:42 6 -> socket:[261202]
lrwx------ 1 root root 64 8月  17 23:38 7 -> socket:[288177]
```

我们使用 strace 指令跟踪一下 redis-cli 进程中的系统调用：  

> strace：strace 常用来跟踪进程执行时的系统调用和所接收的信号。 在 Linux 世界，进程不能直接访问硬件设备，当进程需要访问硬件设备（比如读取磁盘文件，接收网络数据等等）时，必须由用户态模式切换至内核态模式，通过系统调用访问硬件设备。strace 可以跟踪到一个进程产生的系统调用,包括参数，返回值，执行消耗的时间。

```sh
# 先查看 pid
[work@bogon ~]$ ps -ef | grep redis-cli
work      22298  20686  0 8月17 pts/3   00:00:00 /home/work/lib/redis-5.0.5/src/redis-cli -h 127.0.0.1 -p 6379

# 使用 strace 指令跟踪系统调用
[work@bogon ~]$ strace -f -s 65535 -i -T -p 22298
strace: Process 22298 attached
[00007f5c7897b740] read(0, "g", 1)      = 1 <3.799396>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> g\r\33[17C", 28) = 28 <0.000027>
[00007f5c7897b740] read(0, "e", 1)      = 1 <0.079016>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> ge\r\33[18C", 29) = 29 <0.000015>
[00007f5c7897b740] read(0, "t", 1)      = 1 <0.145970>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> get\33[0;90;49m key\33[0m\r\33[19C", 48) = 48 <0.000018>
[00007f5c7897b740] read(0, " ", 1)      = 1 <0.232970>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> get \33[0;90;49mkey\33[0m\r\33[20C", 48) = 48 <0.000025>
[00007f5c7897b740] read(0, "k", 1)      = 1 <0.177562>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> get k\33[0;90;49m \33[0m\r\33[21C", 47) = 47 <0.000026>
[00007f5c7897b740] read(0, "e", 1)      = 1 <0.112225>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> get ke\33[0;90;49m \33[0m\r\33[22C", 48) = 48 <0.000015>
[00007f5c7897b740] read(0, "y", 1)      = 1 <0.057408>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> get key\33[0;90;49m \33[0m\r\33[23C", 49) = 49 <0.000018>
[00007f5c7897b740] read(0, "1", 1)      = 1 <0.232735>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> get key1\33[0;90;49m \33[0m\r\33[24C", 50) = 50 <0.000022>
[00007f5c7897b740] read(0, "\r", 1)     = 1 <0.231926>
[00007f5c7897b6e0] write(1, "\r\33[0K127.0.0.1:6379> get key1\r\33[24C", 35) = 35 <0.000025>
[00007f5c7869397c] ioctl(0, SNDCTL_TMR_CONTINUE or TCSETSF, {B38400 opost isig icanon echo ...}) = 0 <0.000027>
[00007f5c7868ea00] write(1, "\n", 1)    = 1 <0.000021>
[00007f5c7868e5f7] umask(0177)          = 002 <0.000018>
[00007f5c7868e760] open("/home/work/.rediscli_history", O_WRONLY|O_CREAT|O_TRUNC, 0666) = 4 <0.000241>
[00007f5c7868e5f7] umask(002)           = 0177 <0.000018>
[00007f5c7868e607] chmod("/home/work/.rediscli_history", 0600) = 0 <0.000041>
[00007f5c7868e314] fstat(4, {st_mode=S_IFREG|0600, st_size=0, ...}) = 0 <0.000021>
[00007f5c78697e2a] mmap(NULL, 4096, PROT_READ|PROT_WRITE, MAP_PRIVATE|MAP_ANONYMOUS, -1, 0) = 0x7f5c794b5000 <0.000028>
[00007f5c7868ea00] write(4, "keys *\nexit\nset key1 val1\nget key1\n", 35) = 35 <0.000062>
[00007f5c7868f050] close(4)             = 0 <0.000248>
[00007f5c78697eb7] munmap(0x7f5c794b5000, 4096) = 0 <0.000123>
[00007f5c7897b6e0] write(3, "*2\r\n$3\r\nget\r\n$4\r\nkey1\r\n", 23) = 23 <0.000132>
[00007f5c7897b740] read(3, "$4\r\nval1\r\n", 16384) = 10 <0.000473>
[00007f5c7868ea00] write(1, "\"val1\"\n", 7) = 7 <0.000030>
[00007f5c78693a69] ioctl(0, TCGETS, {B38400 opost isig icanon echo ...}) = 0 <0.000020>
[00007f5c78693a69] ioctl(0, TCGETS, {B38400 opost isig icanon echo ...}) = 0 <0.000015>
[00007f5c78693a69] ioctl(0, TCGETS, {B38400 opost isig icanon echo ...}) = 0 <0.000019>
[00007f5c7869397c] ioctl(0, SNDCTL_TMR_CONTINUE or TCSETSF, {B38400 -opost -isig -icanon -echo ...}) = 0 <0.000025>
[00007f5c78694307] ioctl(1, TIOCGWINSZ, {ws_row=61, ws_col=272, ws_xpixel=1904, ws_ypixel=1037}) = 0 <0.000019>
[00007f5c7897b6e0] write(1, "127.0.0.1:6379> ", 16) = 16 <0.000023>
[00007f5c7897b740] read(0,
```

我们可以在上面的系统调用中看到 Redis 通信的内容，符合 Redis 协议的规范。  

下面我们使用 PHP 语言来实现一下 GET 和 SET 操作。  

```php
<?php

function createSocket($ip, $port)
{
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo 'Socket 创建失败';
        return false;
    }
    $res = socket_connect($socket, $ip, $port);
    if ($res === false) {
        echo 'Socket 连接失败';
        return false;
    }
    return $socket;
}

function get($socket, $key)
{
    $len = strlen($key);
    $command = "*2\r\n$3\r\nget\r\n$$len\r\n$key\r\n";
    socket_write($socket, $command, strlen($command));
    $ret = socket_read($socket, 2048);
    return explode("\r\n", $ret)[1];
}

function set($socket, $key, $val)
{
    $keyLen = strlen($key);
    $valLen = strlen($val);
    $command = "*3\r\n$3\r\nset\r\n$$keyLen\r\n$key\r\n$$valLen\r\n$val\r\n";
    socket_write($socket, $command, strlen($command));
    echo 'OK' . PHP_EOL;
}

$ip = '127.0.0.1';
$port = 6379;
$socket = createSocket($ip, $port);
if ($socket === false) return;

// GET
$val = get($socket, 'key1');
if (empty($val)) {
    echo '(nil)' . PHP_EOL;
} else {
    echo $val . PHP_EOL;
}

// SET
set($socket, 'key2', 'val2');

socket_close($socket);
```

执行结果：  

```sh
[work@bogon www]$ php redis.php
val1
OK
```
