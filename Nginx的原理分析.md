# Nginx 的原理分析

Nginx 文档：https://nginx.org/en/docs/beginners_guide.html

### Nginx 安装

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

### Nginx stop、quit、reload 原理

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






