# 整天说三次握手、四次挥手，这次我用 tcpdump 让你亲眼看见 TCP 的报文段

在用 `tcpdump` 命令观察三次握手和四次挥手的报文段之前，先来回顾一下 TCP 报文段的结构。

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/传输层_24.png" width="700"></div>  

再来回顾一下三次握手和四次挥手的流程。  

`三次握手`：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/三次握手_1.png" width="800"></div>  
<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/三次握手_6.png" width="800"></div>  

`四次挥手`：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/四次挥手.png" width="800"></div>  

下面我们用 `socket` 写一个简单的 `TCP client` 和 `TCP server`。  

`server.php`：  

```php
<?php

$server = socket_create(AF_INET, SOCK_STREAM, 0);
socket_bind($server, '0.0.0.0', 1234);
socket_listen($server, 5);

while (1) {
    $connectSocket = socket_accept($server);
    echo "从客户端收到了数据：" . socket_read($connectSocket, 1024) . PHP_EOL;
    socket_write($connectSocket, 'pong', 4);
    sleep(2);
    socket_close($connectSocket);
}
```

`client.php`：

```php
<?php

$client = socket_create(AF_INET, SOCK_STREAM, 0);

if (socket_connect($client, '115.159.111.235', 1234)) {
    socket_write($client, 'ping', 4);
    echo "从服务端收到了数据：" . socket_read($client, 1024) . PHP_EOL;
}

socket_close($client);
```

上面的程序分析：  

1. server 在执行 bind、listen 调用以后进入 `LISTEN` 状态，等待 client 连接。
2. client 调用 connect，通过三次握手之后和 server 建立连接。
3. client 向 server 发送 `ping`，server 向 client 发送 `pong`。
4. client 调用 close，主动关闭连接，通过四次挥手后和 server 之间断开连接。

我们先启动 server，然后在 server 的机器上用 tcpdump 进行抓包，然后启动 client。  

```sh
# 1、启动 server
php server.php
# 2、用 tcpdump 命令抓包
# -S：打印 TCP 数据包的序列号时, 使用绝对的序列号, 而不是相对的序列号
tcpdump -S -nn -vvv -i eth0 port 1234
# 3、启动 client
php client.php
```

tcpdump 抓包的结果如下：  

```sh
[root@VM-0-9-centos ~]# tcpdump -S -nn -vvv -i eth0 port 1234
tcpdump: listening on eth0, link-type EN10MB (Ethernet), capture size 262144 bytes
11:35:40.282576 IP (tos 0x68, ttl 50, id 47514, offset 0, flags [DF], proto TCP (6), length 60)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [S], cksum 0xc1a5 (correct), seq 2099912037, win 28280, options [mss 1412,sackOK,TS val 576627967 ecr 0,nop,wscale 7], length 0
11:35:40.282632 IP (tos 0x0, ttl 64, id 0, offset 0, flags [DF], proto TCP (6), length 60)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [S.], cksum 0x49d6 (correct), seq 2547375254, ack 2099912038, win 28960, options [mss 1460,sackOK,TS val 254669132 ecr 576627967,nop,wscale 7], length 0
11:35:40.319401 IP (tos 0x68, ttl 50, id 47515, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [.], cksum 0xe8bf (correct), seq 2099912038, ack 2547375255, win 221, options [nop,nop,TS val 576628005 ecr 254669132], length 0
11:35:40.319449 IP (tos 0x68, ttl 50, id 47516, offset 0, flags [DF], proto TCP (6), length 56)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [P.], cksum 0x09e3 (correct), seq 2099912038:2099912042, ack 2547375255, win 221, options [nop,nop,TS val 576628005 ecr 254669132], length 4
11:35:40.319466 IP (tos 0x0, ttl 64, id 11750, offset 0, flags [DF], proto TCP (6), length 52)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [.], cksum 0xe890 (correct), seq 2547375255, ack 2099912042, win 227, options [nop,nop,TS val 254669169 ecr 576628005], length 0
11:35:40.319620 IP (tos 0x0, ttl 64, id 11751, offset 0, flags [DF], proto TCP (6), length 56)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [P.], cksum 0x09ad (correct), seq 2547375255:2547375259, ack 2099912042, win 227, options [nop,nop,TS val 254669170 ecr 576628005], length 4
11:35:40.355762 IP (tos 0x68, ttl 50, id 47517, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [.], cksum 0xe86d (correct), seq 2099912042, ack 2547375259, win 221, options [nop,nop,TS val 576628041 ecr 254669170], length 0
11:35:40.355920 IP (tos 0x68, ttl 50, id 47518, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [F.], cksum 0xe86c (correct), seq 2099912042, ack 2547375259, win 221, options [nop,nop,TS val 576628041 ecr 254669170], length 0
11:35:40.395611 IP (tos 0x0, ttl 64, id 11752, offset 0, flags [DF], proto TCP (6), length 52)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [.], cksum 0xe81a (correct), seq 2547375259, ack 2099912043, win 227, options [nop,nop,TS val 254669246 ecr 576628041], length 0
11:35:42.319737 IP (tos 0x0, ttl 64, id 11753, offset 0, flags [DF], proto TCP (6), length 52)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [F.], cksum 0xe095 (correct), seq 2547375259, ack 2099912043, win 227, options [nop,nop,TS val 254671170 ecr 576628041], length 0
11:35:42.355963 IP (tos 0x68, ttl 50, id 26833, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [.], cksum 0xd8cb (correct), seq 2099912043, ack 2547375260, win 221, options [nop,nop,TS val 576630041 ecr 254671170], length 0
```

我们将上面的抓包结果分成 3 部分进行分析：  

1. 三次握手
2. 数据传输
3. 四次挥手

**1、三次握手**  

```sh
11:35:40.282576 IP (tos 0x68, ttl 50, id 47514, offset 0, flags [DF], proto TCP (6), length 60)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [S], cksum 0xc1a5 (correct), seq 2099912037, win 28280, options [mss 1412,sackOK,TS val 576627967 ecr 0,nop,wscale 7], length 0
11:35:40.282632 IP (tos 0x0, ttl 64, id 0, offset 0, flags [DF], proto TCP (6), length 60)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [S.], cksum 0x49d6 (correct), seq 2547375254, ack 2099912038, win 28960, options [mss 1460,sackOK,TS val 254669132 ecr 576627967,nop,wscale 7], length 0
11:35:40.319401 IP (tos 0x68, ttl 50, id 47515, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [.], cksum 0xe8bf (correct), seq 2099912038, ack 2547375255, win 221, options [nop,nop,TS val 576628005 ecr 254669132], length 0
```

过程简化如下：  

```sh
Client                                Server
seq = 2099912037          
                                      seq = 2547375254 ack = 2099912038
seq = 2099912038 ack = 2547375255
```

这里的 Flags [S]/[S.]/[.]

- [S] 代表 SYN
- [.] 代表 ACK，[S.] 就是 SYN + ACK

此时，client 便和 server 建立起了连接。  

**2、数据传输**  

```sh
11:35:40.319449 IP (tos 0x68, ttl 50, id 47516, offset 0, flags [DF], proto TCP (6), length 56)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [P.], cksum 0x09e3 (correct), seq 2099912038:2099912042, ack 2547375255, win 221, options [nop,nop,TS val 576628005 ecr 254669132], length 4
11:35:40.319466 IP (tos 0x0, ttl 64, id 11750, offset 0, flags [DF], proto TCP (6), length 52)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [.], cksum 0xe890 (correct), seq 2547375255, ack 2099912042, win 227, options [nop,nop,TS val 254669169 ecr 576628005], length 0
11:35:40.319620 IP (tos 0x0, ttl 64, id 11751, offset 0, flags [DF], proto TCP (6), length 56)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [P.], cksum 0x09ad (correct), seq 2547375255:2547375259, ack 2099912042, win 227, options [nop,nop,TS val 254669170 ecr 576628005], length 4
11:35:40.355762 IP (tos 0x68, ttl 50, id 47517, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [.], cksum 0xe86d (correct), seq 2099912042, ack 2547375259, win 221, options [nop,nop,TS val 576628041 ecr 254669170], length 0
```

过程简化如下：  

```sh
Client                                Server
seq = 2099912038 ack = 2547375255          
                                      seq = 2547375255 ack = 2099912042
                                      seq = 2547375255 ack = 2099912042
seq = 2099912042 ack = 2547375259
```

数据传输的过程如下：  

1. client 向 server 传输了 `ping`
2. server 收到了 `ping`，先回复 client 一个 ACK，然后向 client 传输一个 `pong`
3. client 收到了 `pong`，回复 server 一个 ACK

此时，client 和 server 之间的数据传输结束了，client 调用 close，主动关闭连接，通过四次挥手后和 server 之间断开连接。  

**3、四次挥手**  

```sh
11:35:40.355920 IP (tos 0x68, ttl 50, id 47518, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [F.], cksum 0xe86c (correct), seq 2099912042, ack 2547375259, win 221, options [nop,nop,TS val 576628041 ecr 254669170], length 0
11:35:40.395611 IP (tos 0x0, ttl 64, id 11752, offset 0, flags [DF], proto TCP (6), length 52)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [.], cksum 0xe81a (correct), seq 2547375259, ack 2099912043, win 227, options [nop,nop,TS val 254669246 ecr 576628041], length 0
11:35:42.319737 IP (tos 0x0, ttl 64, id 11753, offset 0, flags [DF], proto TCP (6), length 52)
    172.17.0.9.1234 > 117.25.25.144.12896: Flags [F.], cksum 0xe095 (correct), seq 2547375259, ack 2099912043, win 227, options [nop,nop,TS val 254671170 ecr 576628041], length 0
11:35:42.355963 IP (tos 0x68, ttl 50, id 26833, offset 0, flags [DF], proto TCP (6), length 52)
    117.25.25.144.12896 > 172.17.0.9.1234: Flags [.], cksum 0xd8cb (correct), seq 2099912043, ack 2547375260, win 221, options [nop,nop,TS val 576630041 ecr 254671170], length 0
```

过程简化如下：

```sh
Client                                Server
seq = 2099912042 ack = 2547375259          
                                      seq = 2547375259 ack = 2099912043
                                      seq = 2547375259 ack = 2099912043
seq = 2099912043 ack = 2547375260
```

- [F] 代表 FIN

1. client 发送 FIN 包
2. server 收到 FIN 包，发送 ACK
3. server 发送 FIN 包
4. client 收到 FIN 包，发送 ACK
5. server 收到 ACK 包

至此，三次握手、数据传输、四次挥手的 tcpdump 结果就分析完了，除了序列号和确认号外，还可以对照着文章开头的握手挥手流程图，分析 client 和 server 的状态，比如 client 发送完 SYN 包后就进入了 `SYN-SENT` 状态。  

