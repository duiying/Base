# Nginx 的原理分析

Nginx 文档：https://nginx.org/en/docs/beginners_guide.html

### 1、Nginx 安装

参考：[源码编译安装 Nginx、PHP8 以及 PHP 常见扩展](https://github.com/duiying/OPS/tree/master/nginx-php-source-install)。  

Nginx 配置如下（只有一个 worker 进程）：  

```
worker_processes  1;

events {
    worker_connections  1024;
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    sendfile        on;

    keepalive_timeout  65;

    server {
        listen       8001;
        server_name  localhost;

        location / {
            root   html;
            index  index.html index.htm;
        }

        error_page   500 502 503 504  /50x.html;
        location = /50x.html {
            root   html;
        }
    }
    include vhosts/*.conf;
}
```

### 2、Nginx stop、quit、reload 原理

我们先看一下 Nginx 文档中的说明：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/Nginx文档.png" width="800"></div>  

文档中说明了：  

1. 如何 stop、quit、reload。
2. reload 的过程：
   - Master 检查配置文件语法；
   - 启动新的 Worker 进程并关闭旧的 Worker 进程；
   - 旧的 Worker 进程停止接受新的连接并继续为当前请求提供服务，直到所有当前请求都结束，然后旧的 Worker 进程退出；

我们首先启动 Nginx 服务：  

```sh
[root@bogon work]# /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
[root@bogon work]# ps -ef | grep nginx | grep -v grep
root       3103      1  0 15:30 ?        00:00:00 nginx: master process /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
work       3104   3103  0 15:30 ?        00:00:00 nginx: worker process
```

可以看到，启动了一个 Master 进程（3103）和一个 Worker 进程（3104），此时 Nginx 服务正常运行。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/Nginx提供服务.png" width="500"></div>  

**观察 stop 时的系统调用**：  

```sh
# 跟踪 Master 进程的系统调用
[root@bogon ~]# strace -f -s 65535 -o stop.log -p 3103
# stop
[root@bogon work]# /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf -s stop
[root@bogon work]# ps -ef | grep nginx | grep -v grep
```

查看 `stop.log`：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/stop的系统调用.png" width="1200"></div>  

其中，`nginx -s reload` 等同于 `kill -s SIGTERM 3103`、`kill -n 15 3103`。  

**同理，我们再来观察 quit 时的系统调用**：  

启动 Nginx：  

```sh
[root@bogon work]# /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
[root@bogon work]# ps -ef | grep nginx | grep -v grep
root       4119      1  0 16:46 ?        00:00:00 nginx: master process /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
work       4120   4119  0 16:46 ?        00:00:00 nginx: worker process
```

```sh
# 跟踪 Master 进程的系统调用
[root@bogon ~]# strace -f -s 65535 -o quit.log -p 4119
# quit
[root@bogon work]# /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf -s quit
[root@bogon work]# ps -ef | grep nginx | grep -v grep
```

查看 `quit.log`：

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/quit的系统调用.png" width="1200"></div>  

其中，`nginx -s quit` 等同于 ` kill -s SIGQUIT 4119`、`kill -n 3 4119`。  

**同理，我们再来观察 reload 时的系统调用**：

启动 Nginx：

```sh
[root@bogon work]# /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
[root@bogon work]# ps -ef | grep nginx | grep -v grep
root       4243      1  0 16:54 ?        00:00:00 nginx: master process /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
work       4244   4243  0 16:54 ?        00:00:00 nginx: worker process
```

```sh
# 跟踪 Master 进程的系统调用
[root@bogon ~]# strace -f -s 65535 -o reload.log -p 4243
# reload
[root@bogon work]# /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf -s reload
# 发现 Master 拉起了一个新的 Worker 进程
[root@bogon ~]# ps -ef | grep nginx | grep -v grep
root       4243      1  0 16:54 ?        00:00:00 nginx: master process /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
work       4268   4243  0 16:56 ?        00:00:00 nginx: worker process
```

查看 `reload.log`：  

```sh
...
# 收到 SIGHUP 信号
4243  --- SIGHUP {si_signo=SIGHUP, si_code=SI_USER, si_pid=4267, si_uid=0} ---
# 重新打开配置文件并读取配置文件的内容
4243  open("/home/work/service/nginx/conf/nginx.conf", O_RDONLY) = 8
pread64(8, "worker_processes  1;\n\nevents {\n    worker_connections  1024;\n}\n\nhttp {\n    include       mime.types;\n    default_type  application/octet-stream;\n\n    sendfile        on;\n\n    keepalive_timeout  65;\n\n    server {\n        listen       80    01;\n        server_name  localhost;\n\n        location / {\n            root   html;\n            index  index.html index.htm;\n        }\n\n        error_page   500 502 503 504  /50x.html;\n        location = /50x.html {\n            root   html;\n        }\n    }\    n    include vhosts/*.conf;\n}\n", 520, 0) = 520
4243 open("/home/work/service/nginx/conf/mime.types", O_RDONLY) = 9
# 克隆出新的子进程
4243  clone(child_stack=NULL, flags=CLONE_CHILD_CLEARTID|CLONE_CHILD_SETTID|SIGCHLD, child_tidptr=0x7f1422f73b10) = 4268
...
``` 

### 2、Nginx 提供静态服务的原理

查看目前的进程信息：  

```sh
[root@bogon ~]# ps -ef | grep nginx | grep -v grep
root       4243      1  0 16:54 ?        00:00:00 nginx: master process /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
work       4268   4243  0 16:56 ?        00:00:00 nginx: worker process
```

由于 Nginx 是 Worker 进程处理请求，所以我们跟踪一下 Worker 进程的系统调用：  

```sh
[root@bogon ~]# strace -f -s 65535 -o static.log -p 4268
```

此时，发起一个 HTTP 请求：  

```sh
[root@bogon ~]# curl 127.0.0.1:8001
<!DOCTYPE html>
<html>
<head>
<title>Welcome to nginx!</title>
<style>
    body {
        width: 35em;
        margin: 0 auto;
        font-family: Tahoma, Verdana, Arial, sans-serif;
    }
</style>
</head>
<body>
<h1>Welcome to nginx!</h1>
<p>If you see this page, the nginx web server is successfully installed and
working. Further configuration is required.</p>

<p>For online documentation and support please refer to
<a href="http://nginx.org/">nginx.org</a>.<br/>
Commercial support is available at
<a href="http://nginx.com/">nginx.com</a>.</p>

<p><em>Thank you for using nginx.</em></p>
</body>
</html>
```

查看系统调用的日志（`vim static.log`）：  

```sh
4268  epoll_wait(10, [{EPOLLIN, {u32=586383376, u64=139724462456848}}], 512, -1) = 1
# 接收客户端连接 3
4268  accept4(6, {sa_family=AF_INET, sin_port=htons(54064), sin_addr=inet_addr("127.0.0.1")}, [112->16], SOCK_NONBLOCK) = 3
4268  epoll_ctl(10, EPOLL_CTL_ADD, 3, {EPOLLIN|EPOLLRDHUP|EPOLLET, {u32=586383840, u64=139724462457312}}) = 0
4268  epoll_wait(10, [{EPOLLIN, {u32=586383840, u64=139724462457312}}], 512, 60000) = 1
# 读取客户端的数据（HTTP 请求报文）
4268  recvfrom(3, "GET / HTTP/1.1\r\nUser-Agent: curl/7.29.0\r\nHost: 127.0.0.1:8001\r\nAccept: */*\r\n\r\n", 1024, 0, NULL, NULL) = 78
4268  stat("/home/work/service/nginx/html/index.html", {st_mode=S_IFREG|0644, st_size=612, ...}) = 0
# 打开静态文件 index.html
4268  open("/home/work/service/nginx/html/index.html", O_RDONLY|O_NONBLOCK) = 4
4268  fstat(4, {st_mode=S_IFREG|0644, st_size=612, ...}) = 0
# 往 socket 中写数据（HTTP 响应报文）
4268  writev(3, [{iov_base="HTTP/1.1 200 OK\r\nServer: nginx/1.19.9\r\nDate: Mon, 18 Oct 2021 09:06:24 GMT\r\nContent-Type: text/html\r\nContent-Length: 612\r\nLast-Modified: Sun, 29 Aug 2021 08:20:28 GMT\r\nConnection: keep-alive\r\nETag: \"612b434c-264\"\r\nAccept-Ranges: bytes\r\n\r\n", iov_len=238}], 1) = 238
4268  sendfile(3, 4, [0] => [612], 612) = 612
# Nginx 记录日志
4268  write(8, "127.0.0.1 - - [18/Oct/2021:17:06:24 +0800] \"GET / HTTP/1.1\" 200 612 \"-\" \"curl/7.29.0\"\n", 86) = 86
4268  close(4)                          = 0
4268  setsockopt(3, SOL_TCP, TCP_NODELAY, [1], 4) = 0
4268  epoll_wait(10, [{EPOLLIN|EPOLLRDHUP, {u32=586383840, u64=139724462457312}}], 512, 65000) = 1
4268  recvfrom(3, "", 1024, 0, NULL, NULL) = 0
4268  close(3)                          = 0
4268  epoll_wait(10,  <detached ...>
```

### 3、Nginx 提供反向代理服务的原理

目前机器上有一个 Swoole 启动的 HTTP 服务：  

```sh
[root@bogon ~]# curl 127.0.0.1:9502
{"code":0,"msg":"success","data":{"user":{"id":1,"name":"duiying","email":"duiying@gmail.com","mobile":"18811112222","position":"研发","mtime":"2021-09-26 21:40:52","ctime":"2021-09-26 21:40:48"}}}
```

我们用 Nginx 来代理一下该 HTTP 服务：  

修改后的 Nginx 配置如下：  

```sh
worker_processes  1;

events {
    worker_connections  1024;
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    sendfile        on;

    keepalive_timeout  65;

    server {
        listen       8001;
        server_name  localhost;

        location / {
            root   html;
            index  index.html index.htm;
            proxy_pass http://127.0.0.1:9502;
        }

        error_page   500 502 503 504  /50x.html;
        location = /50x.html {
            root   html;
        }
    }
    include vhosts/*.conf;
}
```

reload Nginx：  

```sh
[root@bogon ~]# /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf -s reload
```

查看目前的进程信息：

```sh
[root@bogon ~]# ps -ef | grep nginx | grep -v grep
root       4243      1  0 16:54 ?        00:00:00 nginx: master process /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
work       4762   4243  0 17:24 ?        00:00:00 nginx: worker process
```

跟踪 Worker 进程的系统调用：  

```sh
[root@bogon ~]# strace -f -s 65535 -o http_proxy.log -p 4762
```

此时，发起一个 HTTP 请求：  

```sh
[root@bogon ~]# curl 127.0.0.1:8001
{"code":0,"msg":"success","data":{"user":{"id":1,"name":"duiying","email":"duiying@gmail.com","mobile":"18811112222","position":"研发","mtime":"2021-09-26 21:40:52","ctime":"2021-09-26 21:40:48"}}}
```

查看系统调用日志（`vim http_proxy.log`）：  

```sh
4762  epoll_wait(10, [{EPOLLIN, {u32=586383376, u64=139724462456848}}], 512, -1) = 1
# 1、接收客户端的连接，得到 4
4762  accept4(6, {sa_family=AF_INET, sin_port=htons(34244), sin_addr=inet_addr("127.0.0.1")}, [112->16], SOCK_NONBLOCK) = 4
4762  epoll_ctl(10, EPOLL_CTL_ADD, 4, {EPOLLIN|EPOLLRDHUP|EPOLLET, {u32=586383841, u64=139724462457313}}) = 0
4762  epoll_wait(10, [{EPOLLIN, {u32=586383841, u64=139724462457313}}], 512, 60000) = 1
# 2、读取客户端发送的数据（HTTP 请求报文）
4762  recvfrom(4, "GET / HTTP/1.1\r\nUser-Agent: curl/7.29.0\r\nHost: 127.0.0.1:8001\r\nAccept: */*\r\n\r\n", 1024, 0, NULL, NULL) = 78
4762  epoll_ctl(10, EPOLL_CTL_MOD, 4, {EPOLLIN|EPOLLOUT|EPOLLRDHUP|EPOLLET, {u32=586383841, u64=139724462457313}}) = 0
# 3、创建一个 socket，得到 5
4762  socket(AF_INET, SOCK_STREAM, IPPROTO_IP) = 5
4762  ioctl(5, FIONBIO, [1])            = 0
4762  epoll_ctl(10, EPOLL_CTL_ADD, 5, {EPOLLIN|EPOLLOUT|EPOLLRDHUP|EPOLLET, {u32=586384073, u64=139724462457545}}) = 0
# 4、连接真实服务 127.0.0.1:9502
4762  connect(5, {sa_family=AF_INET, sin_port=htons(9502), sin_addr=inet_addr("127.0.0.1")}, 16) = -1 EINPROGRESS (操作现在正在进行)
4762  epoll_wait(10, [{EPOLLOUT, {u32=586383841, u64=139724462457313}}, {EPOLLOUT, {u32=586384073, u64=139724462457545}}], 512, 60000) = 2
4762  getsockopt(5, SOL_SOCKET, SO_ERROR, [0], [4]) = 0
# 5、向真实服务转发客户端的数据（转发 HTTP 请求报文）
4762  writev(5, [{iov_base="GET / HTTP/1.0\r\nHost: 127.0.0.1:9502\r\nConnection: close\r\nUser-Agent: curl/7.29.0\r\nAccept: */*\r\n\r\n", iov_len=97}], 1) = 97
4762  epoll_wait(10, [{EPOLLIN|EPOLLOUT|EPOLLRDHUP, {u32=586384073, u64=139724462457545}}], 512, 60000) = 1
# 6、读取真实服务响应的数据（HTTP 响应报文）
4762  recvfrom(5, "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nServer: swoole-http-server\r\nConnection: close\r\nDate: Mon, 18 Oct 2021 09:29:53 GMT\r\nContent-Length: 199\r\n\r\n{\"code\":0,\"msg\":\"success\",\"data\":{\"user\":{\"id\":1,\"name\":\"duiying\",\"email\":\"duiying@gmail.com\",\"mobile\":\"18811112222\",\"position\":\"\347\240\224\345\217\221\",\"mtime\":\"2021-09-26 21:40:52\",\"ctime\":\"2021-09-26 21:40:48\"}}}", 4096, 0, NULL, NULL) = 363
4762  readv(5, [{iov_base="", iov_len=3733}], 1) = 0
# 7、关闭 5
4762  close(5)                          = 0
# 8、向客户端写回数据（真实服务器返回的 HTTP 响应报文）
4762  writev(4, [{iov_base="HTTP/1.1 200 OK\r\nServer: nginx/1.19.9\r\nDate: Mon, 18 Oct 2021 09:29:53 GMT\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: 199\r\nConnection: keep-alive\r\n\r\n", iov_len=163}, {iov_base="{\"code\":0,\"msg\":\"success\",\"data\":{\"user\":{\"id\":1,\"name\":\"duiying\",\"email\":\"duiying@gmail.com\",\"mobile\":\"18811112222\",\"position\":\"\347\240\224\345\217\221\",\"mtime\":\"2021-09-26 21:40:52\",\"ctime\":\"2021-09-26 21:40:48\"}}}", iov_len=199}], 2) = 362
# Nginx 记录请求日志
4762  write(3, "127.0.0.1 - - [18/Oct/2021:17:29:53 +0800] \"GET / HTTP/1.1\" 200 199 \"-\" \"curl/7.29.0\"\n", 86) = 86
4762  setsockopt(4, SOL_TCP, TCP_NODELAY, [1], 4) = 0
4762  epoll_wait(10, [{EPOLLIN|EPOLLOUT|EPOLLRDHUP, {u32=586383841, u64=139724462457313}}], 512, 65000) = 1
4762  recvfrom(4, "", 1024, 0, NULL, NULL) = 0
# 关闭 4
4762  close(4)                          = 0
4762  epoll_wait(10,  <detached ...>
```

除了代理 HTTP 服务外，Nginx 还可以代理 gRPC、FastCGI 等协议的服务。  

- HTTP：https://nginx.org/en/docs/http/ngx_http_proxy_module.html
- gRPC：https://nginx.org/en/docs/http/ngx_http_grpc_module.html
- FastCGI：https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html

### 4、使用 Go 实现一个静态服务

```go

```

### 5、使用 Python 实现一个静态服务









