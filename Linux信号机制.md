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




