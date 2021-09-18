# Linux 信号机制

### 什么是信号？

信号（signal）是 Linux 进程间通信的一种机制，全称为软中断信号，也被称为软中断。信号本质上是在软件层次上对硬件中断机制的一种模拟。  

与其他进程间通信方式（例如管道、共享内存等）相比，信号所能传递的信息比较粗糙，只是一个整数。但正是由于传递的信息量少，信号也便于管理和使用，可以用于系统管理相关的任务，例如**通知进程终结、中止或者恢复**等。  

如果进程定义了信号的处理函数，那么它将被执行，否则就执行默认的处理函数。  

每种信号用一个整型常量宏表示，以 SIG 开头，比如 SIGCHLD、SIGINT 等，可以用 `kill -l` 来查看支持的信号。  

```sh
$ kill -l
 1) SIGHUP	 2) SIGINT	 3) SIGQUIT	 4) SIGILL	 5) SIGTRAP
 6) SIGABRT	 7) SIGBUS	 8) SIGFPE	 9) SIGKILL	10) SIGUSR1
11) SIGSEGV	12) SIGUSR2	13) SIGPIPE	14) SIGALRM	15) SIGTERM
16) SIGSTKFLT	17) SIGCHLD	18) SIGCONT	19) SIGSTOP	20) SIGTSTP
21) SIGTTIN	22) SIGTTOU	23) SIGURG	24) SIGXCPU	25) SIGXFSZ
26) SIGVTALRM	27) SIGPROF	28) SIGWINCH	29) SIGIO	30) SIGPWR
31) SIGSYS	34) SIGRTMIN	35) SIGRTMIN+1	36) SIGRTMIN+2	37) SIGRTMIN+3
38) SIGRTMIN+4	39) SIGRTMIN+5	40) SIGRTMIN+6	41) SIGRTMIN+7	42) SIGRTMIN+8
43) SIGRTMIN+9	44) SIGRTMIN+10	45) SIGRTMIN+11	46) SIGRTMIN+12	47) SIGRTMIN+13
48) SIGRTMIN+14	49) SIGRTMIN+15	50) SIGRTMAX-14	51) SIGRTMAX-13	52) SIGRTMAX-12
53) SIGRTMAX-11	54) SIGRTMAX-10	55) SIGRTMAX-9	56) SIGRTMAX-8	57) SIGRTMAX-7
58) SIGRTMAX-6	59) SIGRTMAX-5	60) SIGRTMAX-4	61) SIGRTMAX-3	62) SIGRTMAX-2
63) SIGRTMAX-1	64) SIGRTMAX
```

### 常用的中断信号

- SIGTSTP：当用户按下 `ctrl+z` 组合键时，默认动作为终止进程
- SIGTERM：可以被捕捉，SIGTERM 比较友好，通常用来要求程序自己正常退出
- SIGQUIT：`ctrl+\` 发送 SIGQUIT 信号，并且生成 core 文件
- SIGINT：键盘中断。当用户按下 `ctrl+c` 组合键时，默认动作为终止进程
- SIGCHLD：当子进程停止或退出时通知父进程
- SIGUSR1、SIGUSR2：用户自定义信号，默认动作为终止进程
- SIGSTOP：停止进程，不可被捕捉
- SIGKILL：立即终止进程，不可被捕捉

### 什么时候会发送信号？

1. 在终端按下按键产生的中断信号，比如 `ctrl+c` 发送 SIGINT 信号，`ctrl+\` 发送 SIGQUIT 信号，`ctrl+z` 发送 SIGTSTP 信号等等
2. 硬件异常
3. 在终端使用 kill 命令来发送中断信号
4. 在进程中调用 kill、alarm 函数，比如在 PHP 进程中调用 posix_kill、pcntl_alarm 等函数

### 信号的处理方式有哪些？

1. 忽略
2. 捕捉，执行用户定义好的中断信号处理函数（**SIGKILL、SIGSTOP 信号无法被捕捉**）
3. 执行系统默认动作，大部分信号的默认动作就是终止进程

### 用 kill 命令来发送信号

我们先来看下简单的信号处理，我下面启动了一个 PHP 进程，代码如下：  

```php
<?php

echo 'pid：' . posix_getpid() . PHP_EOL;
while (1) {
    ;
}
```

输出结果如下：  

```sh
[work@bogon www]$ php test.php
pid：5915
```

此时我们向该进程发送信号：  

```sh
[work@bogon ~]$  kill -s SIGTSTP 5915
```

于是该进程由前台进程变成了后台进程：  

```sh
[work@bogon www]$ php test.php
pid：5915

[1]+  已停止               php test.php
[work@bogon www]$ jobs
[1]+  已停止               php test.php
```

> jobs 命令：用来查看后台执行的任务列表

### 编写信号处理函数

我下面启动了一个 PHP 进程，代码如下：  

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

function sigHandler($signo)
{
    echo sprintf('pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
}

pcntl_signal(SIGINT,'sigHandler');
pcntl_signal(SIGUSR1,'sigHandler');

while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();

    sleep(1);
}
```

我们按下 `ctrl+c` 或者使用 `kill -s SIGUSR1 6113` 来发送信号，执行结果如下：  

```sh
[work@bogon www]$ php test.php
进程启动了，pid：6113
^Cpid：6113，我收到了一个信号：2
^Cpid：6113，我收到了一个信号：2
pid：6113，我收到了一个信号：10
```

由于上面的 6113 进程注册了 SIGINT 信号的处理函数，所以我们无法通过 `ctrl+c` 来终止该进程了，我通过 `kill -9` 命令终止了上面的 6113 进程。  

要注意的是，SIGKILL、SIGSTOP 信号无法被捕捉，比如下面的程序：  

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

function sigHandler($signo)
{
    echo sprintf('pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
}

// 下面两行代码会报错：PHP Fatal error:  Error installing signal handler
// pcntl_signal(SIGKILL,'sigHandler');
// pcntl_signal(SIGSTOP,'sigHandler');

while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();

    sleep(1);
}
```

除了定义一个回调函数，我们还可以通过 `SIG_DFL`、`SIG_IGN` 来作信号处理。   

- SIG_DFL：信号由该特定信号的默认动作处理
- SIG_IGN：忽略该信号

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

// pcntl_signal(SIGINT,SIG_IGN); // 开启这行代码，ctrl+c 将被忽略
// pcntl_signal(SIGINT,SIG_DFL); // 开启这行代码，ctrl+c 将会终止该进程

while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();

    sleep(1);
}
```

当父进程创建一个子进程的时候，子进程是继承父进程的中断信号处理程序，当然，子进程也可以覆盖父进程的中断信号处理程序，比如：  

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

// 忽略 SIGINT 信号
pcntl_signal(SIGINT, SIG_IGN);

$pid = pcntl_fork();

if ($pid === 0) {
    echo sprintf('子进程启动了，pid：%d' . PHP_EOL, posix_getpid());
    // 子进程已经重设信号处理程序
    pcntl_signal(SIGINT, function($signo) {
        echo sprintf('子进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
    });
}

while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();

    sleep(1);
}
```

当我们分别给父进程和子进程发送 SIGINT 信号时，父进程会忽略该信号，子进程将被回调函数捕捉：  

```sh
[work@bogon www]$ php test.php
进程启动了，pid：6532
子进程启动了，pid：6533
子进程 pid：6533，我收到了一个信号：2
```

### 信号在内核中的表示

上面讨论了信号产生的各种原因，而实际执行信号的处理动作称为信号递达（Delivery），信号从产生到递达之间的状态，称为信号未决（Pending）。  
进程可以选择阻塞（Block）某个信号。被阻塞的信号产生时将保持在未决状态，直到进程解除对此信号的阻塞，才执行递达的动作。  
注意，阻塞和忽略是不同的，只要信号被阻塞就不会递达，而忽略是在递达之后可选的一种处理动作。信号在内核中的表示可以看作是这样的：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/信号表示.png" width="600"></div>  

每个信号都有两个标志位分别表示阻塞和未决，还有一个函数指针表示处理动作。信号产生时，内核在进程控制块中设置该信号的未决标志，直到信号递达才清除该标志。在上图的例子中：  

1. SIGHUP 信号未阻塞也未产生过，当它递达时执行默认处理动作。
2. SIGINT 信号产生过，但正在被阻塞，所以暂时不能递达。虽然它的处理动作是忽略，但在没有解除阻塞之前不能忽略这个信号，因为进程仍有机会改变处理动作之后再解除阻塞。
3. SIGQUIT 信号未产生过，一旦产生 SIGQUIT 信号将被阻塞，它的处理动作是用户自定义函数 sighandler。  

我们可以用 `pcntl_sigprocmask` 函数设置或删除阻塞信号：  

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

pcntl_signal(SIGINT, function($signo) {
    echo sprintf('进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
});

// 设置进程的信号屏蔽字｜信号阻塞集
$sigset = [SIGINT, SIGUSR1];
pcntl_sigprocmask(SIG_BLOCK, $sigset);

for ($i = 0; $i < 10000; $i++) {
    // 必须要有该方法
    pcntl_signal_dispatch();

    sleep(1);
    echo sprintf('休眠了 %d 秒' . PHP_EOL, $i + 1);

    // 5 秒后解除信号屏蔽 $oldSet 会返回之前阻塞的信号集｜信号屏蔽字
    if ($i === 4) {
        pcntl_sigprocmask(SIG_UNBLOCK, [SIGINT, SIGUSR1], $oldSet);
        print_r($oldSet);
    }
}
```

执行结果如下：  

```sh
[work@bogon www]$ php test.php
进程启动了，pid：17379
休眠了 1 秒
^C休眠了 2 秒
^C休眠了 3 秒
^C休眠了 4 秒
^C休眠了 5 秒
Array
(
    [0] => 2
    [1] => 10
)
进程 pid：17379，我收到了一个信号：2
休眠了 6 秒
```

我们可以看到，从第 5 秒开始，进程才会对 SIGINT 信号进行处理。  

### 使用 posix_kill 向进程发送信号

下面我们开启一个父进程和两个子进程，这两个子进程为兄弟进程，分别通过父进程：  

1. 向其中一个子进程每隔 2 秒发送 SIGINT 信号；
2. 向进程组每隔 2 秒发送 SIGINT 信号；

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

pcntl_signal(SIGINT, function($signo) {
    echo sprintf('进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
});

// 子进程 pid 列表
$childPidList = [];

// fork 出一个子进程，称为子进程 1
$pid = pcntl_fork();
$childPidList[] = $pid;

if ($pid > 0) {
    // 再次 fork 出一个子进程，称为子进程 2
    $pid = pcntl_fork();
    $childPidList[] = $pid;

    if ($pid > 0) {
        while (1) {
            // 1、向子进程 1 发送 SIGINT 信号
            // posix_kill($childPidList[0],SIGINT);

            // 2、pid=0 就会向进程组中的每个进程发送信号
            posix_kill(0, SIGINT);
            sleep(2);
        }
    }
}


// 这里是子进程的运行代码
while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();
    echo sprintf('子进程 pid：%d ppid：%d gid：%d' . PHP_EOL, posix_getpid(), posix_getppid(), posix_getgid());
    sleep(1);
}
```

其中情况 1 的执行结果：  

```sh
[work@bogon www]$ php test.php
进程启动了，pid：18947
子进程 pid：18949 ppid：18947 gid：1000
进程 pid：18948，我收到了一个信号：2
子进程 pid：18948 ppid：18947 gid：1000
子进程 pid：18949 ppid：18947 gid：1000
子进程 pid：18948 ppid：18947 gid：1000
进程 pid：18948，我收到了一个信号：2
子进程 pid：18948 ppid：18947 gid：1000
子进程 pid：18949 ppid：18947 gid：1000
子进程 pid：18948 ppid：18947 gid：1000
子进程 pid：18949 ppid：18947 gid：1000
进程 pid：18948，我收到了一个信号：2
```

情况 2 的执行结果：  

```sh
[work@bogon www]$ php test.php
进程启动了，pid：18998
进程 pid：18999，我收到了一个信号：2
子进程 pid：18999 ppid：18998 gid：1000
进程 pid：19000，我收到了一个信号：2
子进程 pid：19000 ppid：18998 gid：1000
子进程 pid：18999 ppid：18998 gid：1000
子进程 pid：19000 ppid：18998 gid：1000
进程 pid：18999，我收到了一个信号：2
子进程 pid：18999 ppid：18998 gid：1000
进程 pid：19000，我收到了一个信号：2
```

### 使用 pcntl_alarm 向进程发送 SIGALRM 信号

比如 2 秒后发送一个 SIGALRM 信号：  

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

pcntl_signal(SIGALRM, function($signo) {
    echo sprintf('进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
});

// 2 秒后会发送 SIGALRM 信号
pcntl_alarm(2);

while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();
    echo sprintf('进程 pid：%d 正在执行' . PHP_EOL, posix_getpid());
    sleep(1);
}
```

执行结果如下：  

```sh
[work@bogon www]$ php test.php
进程启动了，pid：19120
进程 pid：19120 正在执行
进程 pid：19120 正在执行
进程 pid：19120，我收到了一个信号：14
进程 pid：19120 正在执行
```

上面的 `pcntl_alarm(2)` 只会发送一次 SIGALRM 信号，我们可以改成每隔 2 秒都发送一次 SIGALRM 信号：  

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

pcntl_signal(SIGALRM, function($signo) {
    echo sprintf('进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
    pcntl_alarm(2);
});

pcntl_alarm(2);

while (1) {
    // 必须要有该方法
    pcntl_signal_dispatch();
    echo sprintf('进程 pid：%d 正在执行' . PHP_EOL, posix_getpid());
    sleep(1);
}
```

其中 `pcntl_alarm(0)` 会清理掉设置的定时。  





执行结果如下：

```sh
[work@bogon www]$ php test.php
进程启动了，pid：19157
进程 pid：19157 正在执行
进程 pid：19157 正在执行
进程 pid：19157，我收到了一个信号：14
进程 pid：19157 正在执行
进程 pid：19157 正在执行
进程 pid：19157，我收到了一个信号：14
```

### 如何捕获子进程的退出信号

主要是信号：**SIGCHLD**。  

下面我们通过父进程捕捉到子进程的退出信号：  

```php
<?php

echo sprintf('进程启动了，pid：%d' . PHP_EOL, posix_getpid());

pcntl_signal(SIGCHLD, function($signo) {
    echo sprintf('进程 pid：%d，我收到了一个信号：%d' . PHP_EOL, posix_getpid(), $signo);
    $pid = pcntl_waitpid(-1, $status, WNOHANG | WUNTRACED);
    if ($pid > 0) {
        echo sprintf('我是父进程，我捕捉到子进程 pid：%d 退出了' . PHP_EOL, $pid, $signo);
    }
});

$pid = pcntl_fork();

// 父进程
if ($pid > 0) {
    while (1){
        // 必须要有该方法
        pcntl_signal_dispatch();
        echo sprintf('父进程 pid：%d 正在执行' . PHP_EOL, posix_getpid());
        sleep(1);

    }
}
// 子进程
else {
    echo sprintf('子进程 pid：%d 即将退出' . PHP_EOL, posix_getpid());
    exit(10);
}
```

执行结果如下：  

```sh
[work@bogon www]$ php test.php
进程启动了，pid：19417
父进程 pid：19417 正在执行
子进程 pid：19418 即将退出
进程 pid：19417，我收到了一个信号：17
我是父进程，我捕捉到子进程 pid：19418 退出了
```

