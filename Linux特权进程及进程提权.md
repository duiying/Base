# Linux 特权进程及进程提权

Linux 是一种安全的操作系统，它把所有的系统权限都赋予了一个单一的 root 用户，只给普通用户保留有限的权限。root 用户拥有超级管理员权限，可以安装软件、允许某些服务、管理用户等。  

作为普通用户，如果想执行某些只有管理员才有权限的操作，以前只有两种办法：一是通过 sudo 提升权限，如果用户很多，配置管理和权限控制会很麻烦；二是通过 SUID（Set User ID on execution）来实现，它可以让普通用户允许一个 owner 为 root 的可执行文件时具有 root 的权限。  

SUID 的概念比较晦涩难懂，举个例子就明白了，以常用的 passwd 命令为例，修改用户密码是需要 root 权限的，但普通用户却可以通过这个命令来修改密码，这就是因为 /bin/passwd 被设置了 SUID 标识，所以普通用户执行 passwd 命令时，进程的 owner 就是 passwd 的所有者，也就是 root 用户。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/suid.png" width="1000"></div>  

**如何给进程提权、降权？**  

我们用 root 用户建一个文件：  

```sh
[root@localhost www]# touch suid.txt
[root@localhost www]# ll
总用量 4
-rw-r--r-- 1 root root   0 9月  14 16:15 suid.txt
-rw-rw-r-- 1 work work 735 9月  14 15:58 test.php
```

按理说这个 `suid.txt` 文件，work 账户是没有写权限的。  

```sh
[work@localhost www]$ echo 'suid' >> suid.txt
-bash: suid.txt: 权限不够
```

下面我们给进程提权，在 work 下建一个 `test.php` 脚本，让该进程有写权限：  

```php
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
```

然后，给 PHP 解释器设置 SUID 标识：  

```sh
[root@localhost www]# chmod u+s /home/work/service/php74/bin/php
```

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/php_suid.png" width="1000"></div>  

最后执行该脚本，执行结果如下：  

```sh
[work@localhost www]$ php test.php
提权前：uid 1000 euid 0
提权后：uid 0 euid 0
降权后：uid 1000 euid 1000
降权后没有写权限
```








