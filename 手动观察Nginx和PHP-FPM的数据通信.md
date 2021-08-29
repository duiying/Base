# 手动观察 Nginx 和 PHP-FPM 的数据通信

### 环境准备

已经搭建好了 Nginx 和 PHP-FPM 环境，并且已经配置好了两者的通信，如下所示：  

```sh
[root@bogon work]# ps -ef | grep nginx
root      92928      1  0 01:22 ?        00:00:00 nginx: master process /home/work/service/nginx/sbin/nginx -c /home/work/service/nginx/conf/nginx.conf
work      93208  92928  0 01:31 ?        00:00:00 nginx: worker process

[root@bogon work]# ps -ef | grep fpm
root      93163      1  0 01:29 ?        00:00:00 php-fpm: master process (/home/work/service/php74/etc/php-fpm.conf)
work      93164  93163  0 01:29 ?        00:00:00 php-fpm: pool www
work      93165  93163  0 01:29 ?        00:00:00 php-fpm: pool www
```

Nginx 对外端口是 8000，收到 PHP 请求将请求转发给 PHP-FPM，PHP-FPM 的端口是 9000，查看本机监听的服务端口。  

```
[root@bogon work]# netstat -luntp | grep fpm
tcp        0      0 127.0.0.1:9000          0.0.0.0:*               LISTEN      93163/php-fpm: mast
[root@bogon work]# netstat -luntp | grep nginx
tcp        0      0 0.0.0.0:8000            0.0.0.0:*               LISTEN      92928/nginx: master
tcp        0      0 0.0.0.0:8001            0.0.0.0:*               LISTEN      92928/nginx: master
```

Nginx 配置如下：  

```sh
[root@bogon vhosts]# cat /home/work/service/nginx/conf/vhosts/test.conf
server {
    listen       8000;
    server_name  localhost;

    root /home/work/www;
    index index.html index.htm index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        try_files $uri =404;
    }
}
```

PHP 脚本内容如下：  

```php
[root@bogon vhosts]# cat /home/work/www/index.php
<?php

echo json_encode(['key' => 'val']);
```

### 查看进程的系统调用

我们使用 strace 指令跟踪一下 Nginx 的 Worker 进程中的系统调用（Nginx 的网络请求是由 Worker 进程处理的）：  

```sh
[root@bogon work]# strace -f -s 65535 -i -T -p 93208
strace: Process 93208 attached
[00007fd6ce30ef23] epoll_wait(11,
```

我们再使用 strace 指令跟踪一下 FPM 的 Worker 进程中的系统调用（FPM 的网络请求是由 Worker 进程处理的）：  

```sh
[root@bogon work]#  strace -f -s 65535 -i -T -p 93164
strace: Process 93164 attached
[00007fe9b6992600] accept(5,
```

此时我们用 HTTP 工具 Paw 发起一个请求，此时：  

Nginx 调用如下：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/Nginx通信.png" width="1000"></div>  

FPM 调用如下：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/FPM通信.png" width="1000"></div>  
