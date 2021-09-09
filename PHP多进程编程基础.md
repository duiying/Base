# PHP 多进程编程基础

### 了解关于进程的一些 ID

```php
<?php

echo sprintf('pid = %d' . PHP_EOL, posix_getpid());                 // 进程 ID
echo sprintf('ppid = %d' . PHP_EOL, posix_getppid());               // 父进程 ID
echo sprintf('gid = %d' . PHP_EOL, posix_getgid());                 // 进程组 ID
echo sprintf('uid = %d' . PHP_EOL, posix_getuid());                 // 进程实际用户 ID
echo sprintf('sid = %d' . PHP_EOL, posix_getsid(posix_getpid()));   // 会话 ID
```

在 work 账户下执行结果：  

```sh
[work@localhost www]$ id root
uid=0(root) gid=0(root) 组=0(root)
[work@localhost www]$ id work
uid=1000(work) gid=1000(work) 组=1000(work)
[work@localhost www]$ echo $$
83264
[work@localhost www]$ php test.php
pid = 83671
ppid = 83264
gid = 1000
uid = 1000
sid = 83264
```

可以看到当前进程的 ppid 为 Bash 进程的 ID，原因请看：[Bash 进程的启动过程](Bash进程的启动过程.md)。  

在 root 账户下执行结果：  

```sh
[root@localhost www]# php test.php
pid = 83749
ppid = 83710
gid = 0
uid = 0
sid = 83316
```

发现在 root 账户下，uid 和 gid 变成了 root 账户对应的用户 ID 和组 ID。  

### 如何创建一个子进程？

关于 `pcntl_fork`：  

`pcntl_fork` 会新开一个子进程，在主进程会中返回子进程的 PID，在子进程中会返回 0，如果 fork 失败会在主进程返回 -1。  

fork 的时候，采用的是**写时复制（COW）**。  

```php
<?php

echo '父进程 ID：' . posix_getpid() . PHP_EOL;

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
} else {
    pcntl_wait($status);
    echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());
    sleep(3600);
}

echo sprintf('PID：%d' . PHP_EOL, posix_getpid());
```

执行结果如下：  

```sh
[work@localhost www]$ php test.php
父进程 ID：83972
子进程执行，ID：83973，父进程 ID：83972
PID：83973
父进程执行，ID：83972
```

此时，子进程退出了，父进程通过 `pcntl_wait` 回收了子进程占用的系统资源，那么此时只有一个父进程处于 `sleep` 状态：    

```sh
[work@localhost www]$ ps -aux | grep test | grep -v 'grep'
work      83972  0.0  0.3 240988 12704 pts/2    S+   14:24   0:00 php test.php
[work@localhost www]$ ps -ef | grep test | grep -v 'grep'
work      83972  83931  0 14:24 pts/2    00:00:00 php test.php
```

### 常见的进程状态都有哪些？

- R：运行
- S：休眠
- Z：僵尸
- T：停止
- D：不可中断

从上面的 `ps` 结果来看，父进程由于执行到了 `sleep`，进程处于休眠状态。  

### 什么是孤儿进程？

父进程已经退出，而它的一个或多个子进程还在运行，这些子进程将成为孤儿进程，孤儿进程会被 init 进程（进程号为 1）收养，并由 init 进程完成对它们的状态收集工作。  

```php
<?php

echo '父进程 ID：' . posix_getpid() . PHP_EOL;

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
    sleep(3600);
} else {
    echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());
}

echo sprintf('PID：%d' . PHP_EOL, posix_getpid());
```

执行结果如下：

```sh
[work@localhost www]$ php test.php
父进程 ID：85067
父进程执行，ID：85067
PID：85067
子进程执行，ID：85068，父进程 ID：85067
```

我们查看进程状态，发现子进程（PID=85068）的父进程 ID 变成了 1。  

```sh
[work@localhost www]$ ps -ef | grep test | grep -v 'grep'
work      85068      1  0 15:38 pts/2    00:00:00 php test.php
[work@localhost www]$ ps -aux | grep test | grep -v 'grep'
work      85068  0.0  0.1 240988  5860 pts/2    S    15:38   0:00 php test.php
```

当孤儿进程结束之后，init 进程会回收其占用的资源，因此孤儿进程其实**没有多少危害**。

### 什么是僵尸进程？

在每个进程退出的时候，内核释放该进程所有的资源，包括打开的文件、占用的内存等。但是仍然为其保留一定的信息（包括进程号、退出状态、运行时间等）。直到父进程通过 wait / waitpid 来获取时才释放。  

如果一个进程通过 fork 创建了子进程，如果子进程退出而父进程没有调用 wait() 或 waitpid() 获取子进程的状态信息，那么子进程占用的资源就不会释放，系统能使用的进程号是有限的，如果有大量的僵尸进程，将因为没有可用的进程号而导致无法创建新的进程。    

如果父进程退出了，子进程将由僵尸进程变成孤儿进程，从而被 init 进程接管，init 进程会释放其占用的资源。  

```php
<?php

echo '父进程 ID：' . posix_getpid() . PHP_EOL;

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
} else {
    echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());
    sleep(3600);
}

echo sprintf('PID：%d' . PHP_EOL, posix_getpid());
```

执行结果如下：

```sh
[work@localhost www]$ php test.php
父进程 ID：85340
父进程执行，ID：85340
子进程执行，ID：85341，父进程 ID：85340
PID：85341
```

我们查看子进程的进程状态，发现变成了 Z。  

```sh
[work@localhost www]$ ps -aux | grep 85341 | grep -v 'grep'
work      85341  0.0  0.0      0     0 pts/2    Z+   15:58   0:00 [php] <defunct>
```

此时，`/proc/85341` 下还有文件，表示该进程还有部分信息没有被释放，如果我们此时停掉父进程，此时子进程被 init 进程接管，释放占用的资源，`/proc/85341` 目录下变成空了。

### 总结

- **了解如何获取进程的一些 ID 信息**（进程 ID、父进程 ID、进程组 ID、进程实际用户 ID、会话 ID）
- **如何创建一个子进程？**（pcntl_fork）
- **常见的进程状态都有哪些？**
- **什么是孤儿进程？孤儿进程的危害？**
- **什么是僵尸进程？僵尸进程的危害？**

