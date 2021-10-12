# Socket

前面提到的管道、消息队列、共享内存、信号量和信号都是在同一台主机上进行进程间通信，那要想**与其他主机上的进程进行通信，就需要 socket 通信了**。  

实际上，socket 通信不仅可以实现跨主机的进程间通信，也可以进行本机的进程间通信。  

我们来看看创建 socket 的系统调用：  

```c
#include <sys/socket.h>
int socket(int domain, int type, int protocol);
```

参数解释：  

- domain：协议族。比如 IPv4：AF_INET；IPv6：AF_INET6；本机：AF_LOCAL/AF_UNIX
- type：通信特性。比如 SOCK_STREAM 表示的是字节流，对应 TCP；SOCK_DGRAM 表示的是数据报，对应 UDP；SOCK_RAW 表示的是原始套接字
- protocal：原本是用来指定通信协议，现在基本废弃。因为协议已经通过前面两个参数指定完成，protocol 目前一般写成 0 即可

根据创建 socket 类型的不同，通信的方式也就不同：  

1. 实现 TCP 字节流通信：socket 类型是 AF_INET 和 SOCK_STREAM；
2. 实现 UDP 数据报通信：socket 类型是 AF_INET 和 SOCK_DGRAM；
3. 实现本地进程间通信：
  - 本地字节流 socket 类型是 AF_LOCAL 和 SOCK_STREAM
  - 本地数据报 socket 类型是 AF_LOCAL 和 SOCK_DGRAM
  - （另外，AF_UNIX 和 AF_LOCAL 是等价的，所以 AF_UNIX 也属于本地 socket）

**接下来，简单说一下这三种通信的编程模式。**  

### 1、针对 TCP 协议通信的 socket 编程模型

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/TCP通信.png" width="600"></div>  

- 服务端和客户端初始化 socket，得到文件描述符；
- 服务端调用 bind，将绑定在 IP 地址和端口;
- 服务端调用 listen，进行监听；
- 服务端调用 accept，等待客户端连接；
- 客户端调用 connect，向服务器端的地址和端口发起连接请求；
- 服务端 accept 返回用于传输的 socket 的文件描述符；
- 客户端调用 write 写入数据；服务端调用 read 读取数据；
- 客户端断开连接时，会调用 close，那么服务端 read 读取数据的时候，就会读取到了 EOF，待处理完数据后，服务端调用 close，表示连接关闭。

这里需要注意的是，服务端调用 accept 时，连接成功了会返回一个**已完成连接的 socket**，后续用来传输数据。  

所以，监听的 socket 和真正用来传送数据的 socket，是「**两个**」 socket，一个叫作**监听 socket**，一个叫作**已完成连接 socket**。  

成功连接建立之后，双方开始通过 read 和 write 函数来读写数据，就像往一个文件流里面写东西一样。  

### 2、针对 UDP 协议通信的 socket 编程模型

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/UDP通信.png" width="300"></div>

UDP 是没有连接的，所以不需要三次握手，也就不需要像 TCP 调用 listen 和 connect，但是 UDP 的交互仍然需要 IP 地址和端口号，因此也需要 bind。  

对于 UDP 来说，不需要要维护连接，那么也就没有所谓的发送方和接收方，甚至都不存在客户端和服务端的概念，只要有一个 socket 多台机器就可以任意通信，因此每一个 UDP 的 socket 都需要 bind。  

另外，每次通信时，调用 sendto 和 recvfrom，都要传入目标主机的 IP 地址和端口。  

### 3、针对本地进程间通信的 socket 编程模型

本地 socket 被用于在**同一台主机上进程间通信**的场景：  

- 本地 socket 的编程接口和 IPv4 、IPv6 套接字编程接口是一致的，可以支持「字节流」和「数据报」两种协议；
- 本地 socket 的实现效率大大高于 IPv4 和 IPv6 的字节流、数据报 socket 实现；

对于本地字节流 socket，其 socket 类型是 AF_LOCAL 和 SOCK_STREAM。  

对于本地数据报 socket，其 socket 类型是 AF_LOCAL 和 SOCK_DGRAM。  

本地字节流 socket 和 本地数据报 socket 在 bind 的时候，不像 TCP 和 UDP 要绑定 IP 地址和端口，而是**绑定一个本地文件**，这也就是它们之间的最大区别。

### PHP 实践

PHP 关于 socket 通信封装了 `stream`、`socket` 两个系列的函数，它们底层的系统调用差不多。  

**1、小试牛刀，通过父进程向子进程发送数据**  

```php
<?php

// 创建一对网络套接字
$sockets = stream_socket_pair(AF_UNIX, SOCK_STREAM, 0);

$readFd     = $sockets[0];  // 读 socket
$writeFd    = $sockets[1];  // 写 socket

$pid = pcntl_fork();

// 子进程从 socket 中读取数据
if ($pid === 0) {
    while (1) {
        $data = fread($readFd, 128);
        if ($data) {
            echo sprintf('子进程从 socket 中读取到了数据：%s' . PHP_EOL, $data);
        }
        // 当收到 exit 时，退出循环
        if (trim($data) === 'exit') {
            break;
        }
    }
    exit;
}

// 父进程获取终端的输入，然后往 socket 中写入数据
while (1) {
    $data = fread(STDIN, 128);
    if ($data) {
        fwrite($writeFd, $data, strlen($data));
    }
    // 当收到 exit 时，退出循环
    if (trim($data) === 'exit') {
        break;
    }
}

$pid = pcntl_wait($status);
if ($pid > 0) {
    echo "子进程 pid：$pid 退出了" . PHP_EOL;
}
```

执行结果如下：  

```bash
[work@bogon www]$ php test.php
hello
子进程从 socket 中读取到了数据：hello

world
子进程从 socket 中读取到了数据：world

exit
子进程从 socket 中读取到了数据：exit

子进程 pid：2604 退出了
```



