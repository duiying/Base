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

**所以，我们在进行多进程编程的时候，一定要用 wait 来回收退出的子进程。**  

### 进程的退出

PHP 进程启动后，在以下情况会退出：  

- 运行到最后一行语句
- 运行时遇到 return 时
- 运行时遇到 exit() 函数的时候
- 程序异常的时候
- 进程接收到中断信号

无论进程怎么退出，它都有一个终止状态码，进程结束不会释放所有资源，父进程可以通过 wait 相关函数来获取进程的终止状态码同时释放子进程占用的资源，防止产生僵尸进程。  

```php
<?php

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
    // 一般 0 表示成功 -1 表示失败，最大为 255
    exit(8);
} else {
    echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());
    pcntl_wait($status);
    echo '子进程退出了，终止状态码：' . pcntl_wexitstatus($status) . PHP_EOL;
}
```

执行结果如下：  

```sh
[work@localhost www]$ php test.php
父进程执行，ID：85696
子进程执行，ID：85697，父进程 ID：85696
子进程退出了，终止状态码：8
```

**如何让 wait 非阻塞立即返回？**  

```php
<?php

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
} else {
    echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());
    pcntl_wait($status, WUNTRACED);
    echo sprintf('父进程没有阻塞，ID：%d' . PHP_EOL, posix_getpid());
    sleep(30);
}
```

如何让 wait 非阻塞？  

```php
pcntl_wait($status, WNOHANG | WUNTRACED);
```

**如何判断子进程的退出方式？**  

```php
<?php

$pid = pcntl_fork();

if ($pid === -1) {
    echo 'fork 失败' . PHP_EOL;
    exit;
} elseif ($pid === 0) {
    echo sprintf('子进程执行，ID：%d，父进程 ID：%d' . PHP_EOL, posix_getpid(), posix_getppid());
    sleep(60);
} else {
    while (1) {
        echo sprintf('父进程执行，ID：%d' . PHP_EOL, posix_getpid());

        $pid = pcntl_wait($status, WNOHANG | WUNTRACED);

        if ($pid > 0) {
            // 正常退出
            if (pcntl_wifexited($status)) {
                echo sprintf('子进程正常退出，status：%d' . PHP_EOL, pcntl_wexitstatus($status));
                break;
            }
            // 中断退出
            else if (pcntl_wifsignaled($status)){
                echo sprintf('子进程中断退出 1，status：%d' . PHP_EOL, pcntl_wtermsig($status));
                break;
            }
            // 一般是发送 SIGSTOP SIGTSTP 让进程停止
            else if (pcntl_wifstopped($status)) {
                echo sprintf('子进程中断退出 2，status：%d' . PHP_EOL, pcntl_wstopsig($status));
                break;
            }
        }

        sleep(3);
    }
}
```

- 如果程序正常执行完成，会打印：子进程正常退出，status：0
- 如果 `kill -SIGUSR1 子进程 PID`，会打印：子进程中断退出 1，status：10
- 如果 `kill -SIGSTOP 子进程 PID`，会打印：子进程中断退出 2，status：19

**我们可以用 `kill -l` 查看 kill 支持的信号。**（kill 命令的作用是发送指定的信号到相应进程）

### 使用 pcntl_exec 在当前进程空间执行指定程序

准备两个 PHP 文件，`father.php` 和 `son.php`：  

`father.php`：  

```php
<?php

function printIdInfo($text = '')
{
    echo "$text begin" . PHP_EOL;
    echo sprintf('pid = %d' . PHP_EOL, posix_getpid());                 // 进程 ID
    echo sprintf('ppid = %d' . PHP_EOL, posix_getppid());               // 父进程 ID
    echo sprintf('gid = %d' . PHP_EOL, posix_getgid());                 // 进程组 ID
    echo sprintf('uid = %d' . PHP_EOL, posix_getuid());                 // 进程实际用户 ID
    echo sprintf('sid = %d' . PHP_EOL, posix_getsid(posix_getpid()));   // 会话 ID
    echo "$text end" . PHP_EOL;
}
printIdInfo('父进程');
$pid = pcntl_fork();
if ($pid === 0) {
    pcntl_exec('/home/work/service/php74/bin/php', ['/home/work/www/son.php', 'a', 'b', 'c']);

    // 下面这行不会执行
    echo 'hello' . PHP_EOL;
}
$pid = pcntl_wait($status);
if ($pid > 0) {
    echo "子进程 pid：$pid 退出了" . PHP_EOL;
}
```

`son.php`：

```php
<?php

function printIdInfo($text = '')
{
    echo "$text begin" . PHP_EOL;
    echo sprintf('pid = %d' . PHP_EOL, posix_getpid());                 // 进程 ID
    echo sprintf('ppid = %d' . PHP_EOL, posix_getppid());               // 父进程 ID
    echo sprintf('gid = %d' . PHP_EOL, posix_getgid());                 // 进程组 ID
    echo sprintf('uid = %d' . PHP_EOL, posix_getuid());                 // 进程实际用户 ID
    echo sprintf('sid = %d' . PHP_EOL, posix_getsid(posix_getpid()));   // 会话 ID
    echo "$text end" . PHP_EOL;
}

printIdInfo('子进程');
print_r($argv);
```

执行 `strace -f -s 65535 -o exec.log php father.php`，打印结果：  

```sh
父进程 begin
pid = 108554
ppid = 108552
gid = 1000
uid = 1000
sid = 104321
父进程 end
子进程 begin
pid = 108555
ppid = 108554
gid = 1000
uid = 1000
sid = 104321
子进程 end
Array
(
    [0] => /home/work/www/son.php
    [1] => a
    [2] => b
    [3] => c
)
子进程 pid：108555 退出了
```

查看系统调用日志，可以看到底层先是调用了 clone 创建了一个子进程，然后调用 execve 将参数传给了 PHP 解释器。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/pcntl_exec.png" width="1000"></div>  

### 手动修改进程的优先级

使用 `pcntl_setpriority` 函数。  

priority 通常为 -20 至 20 这个范围内的值。默认优先级是 0，值越小代表优先级越高，比如我们控制子进程优先级比父进程优先级低，即子进程的 nice 值更大：  

```php
<?php

$nice = $argv[1];
$start = time();
$i = 0;

$pid = pcntl_fork();
if ($pid === 0) {
   echo sprintf('子进程执行 pid=%d' . PHP_EOL, posix_getpid());
    pcntl_setpriority($nice, posix_getpid());
} else {
    echo sprintf('父进程执行 pid=%d' . PHP_EOL, posix_getpid());
}

echo sprintf('进程执行 pid=%d nice=%d' . PHP_EOL, posix_getpid(), pcntl_getpriority());
```

在 top 命令中的 NI 值即代表进程的优先级。

### 下面代码会创建几个进程？每个进程的变量值是多少？请详细描述下面程序的执行过程？  

```php
<?php

echo sprintf('当前进程 pid=%d' . PHP_EOL, posix_getpid());

$count = 10;

for ($i = 0; $i < 2; $i++) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        $count += 1;
    } else {
        $count *= 10;
    }
}

echo sprintf('pid=%d count=%d' . PHP_EOL, posix_getpid(), $count);
```

执行结果如下：  

```sh
[work@localhost www]$ php test.php
当前进程 pid=109700
pid=109700 count=1000
pid=109701 count=110
pid=109703 count=12
pid=109702 count=101
```

1、当前进程 pid=109700，我们称为进程 0，进程 0 fork 了一个子进程，称为进程 1，此时 `$i = 0; $count = 10`  

2、CPU 继续执行进程 0，此时 `$i = 0; $count = 100`，然后 `$i++`，此时 `$i = 1; $count = 100`  

3、CPU 继续执行进程 0，进程 0 fork 了一个子进程，称为进程 2，此时 `$i = 1; $count = 100`，进程 0 继续执行，此时 `$i = 1; $count = 1000`，然后 `$i++`，此时进程 0 退出循环  

4、CPU 执行进程 2，`$count += 1`，此时 `$i = 1; $count = 101`，然后 `$i++`，此时进程 2 退出循环  

5、CPU 执行进程 1，`$count += 1`，此时 `$i = 0; $count = 11`，然后 `$i++`，此时 `$i = 1; $count = 11`，然后执行下一次循环  

6、进程 1 执行到了 `pcntl_fork`，进程 1 fork 出了一个子进程，称为进程 3，进程 1 继续执行 `$count *= 10`，此时进程 1 `$i = 1; $count = 110`，然后进程 1 `$i++`，退出了循环  

7、进程 3 执行 `$count += 1`，此时进程 3 `$i = 1; $count = 12`，，然后进程 3 `$i++`，退出了循环  

所以：一共存在 4 个进程，产生了 3 个新的进程，进程 1 和进程 2 是当前进程的子进程，进程 3 是进程 1 的子进程。  

### 总结

- **了解如何获取进程的一些 ID 信息**（进程 ID、父进程 ID、进程组 ID、进程实际用户 ID、会话 ID）
- **如何创建一个子进程？**（pcntl_fork）
- **常见的进程状态都有哪些？**
- **什么是孤儿进程？孤儿进程的危害？**
- **什么是僵尸进程？僵尸进程的危害？**
- **如何让 wait 非阻塞立即返回？**
- **如何判断子进程的退出方式？**
- **pcntl_exec 的作用是什么？它底层调用了哪个函数？**（作用是在当前进程空间执行指定程序，底层调用了 clone 和 exec 函数）
- **在 top 命令中的 NI 值代表什么？**（进程的优先级，nice 值越大，进程调度的优先级越低）

