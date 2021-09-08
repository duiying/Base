# Bash 进程的启动过程

目前我们只开了一个连接会话，用 `pstree -ap` 命令查看当前的进程信息。  

```sh
systemd,1 --switched-root --system --deserialize 22
├─sshd,1055 -D
│   └─sshd,71634
│       └─sshd,71636
│           └─bash,71637
│               └─pstree,71697 -ap
...
```

用 `echo $$` 命令查看当前 Bash 进程的 ID：  

```sh
[work@localhost ~]$ echo $$
71637
```

此时，我们跟踪一下 sshd 进程的系统调用。  

```sh
[root@localhost www]# strace -f -s 65535 -o bash.log -p 1055
```

然后打开会话 2，去连接该主机。  

```sh
ssh work@xxx.xxx.xxx.xxx
```

查看会话 2 当前 Bash 进程的  ID：  

```sh
[work@localhost ~]$ echo $$
73914
```

此时，当前系统的进程信息如下：  

```
systemd,1 --switched-root --system --deserialize 22
├─sshd,1055 -D
│   ├─sshd,71634
│   │   └─sshd,71636
│   │       └─bash,71637
│   │           └─su,73860 root
│   │               └─bash,73861
│   └─sshd,73906
│       └─sshd,73911
│           └─bash,73914
│               └─pstree,74147 -ap
...
```

可以发现，多了一个 sshd 进程 73906。  

我们查看系统调用日志，`vim bash.log`：  

1、首先系统中有一个 pid=1055 的 sshd 进程，这个进程通过 select IO 多路复用 accept 客户端的连接。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/ssh_strace_0.png" width="1000"></div>  

当接收到连接请求时，通过 fork/clone 出一个子进程，pid=73906，该子进程调用 execve 函数：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/ssh_strace_1.png" width="1000"></div>  

2、当输入密码，登录上系统时。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/ssh_strace_2.png" width="1000"></div>  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/ssh_strace_3.png" width="1000"></div>  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/ssh_strace_4.png" width="1000"></div>  

创建了一个 ssd 的子进程，pid=73911，一个 Bash 进程，pid=73914。  

3、我们在会话 2 当前 Bash 执行 `ls` 命令后，系统调用如下：  

在当前 Bash 进程 fork/clone 出一个子进程，pid=77126，自己是一个进程组。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/ssh_strace_5.png" width="1000"></div>  

然后执行 execve 函数。  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/ssh_strace_6.png" width="1000"></div>  

**由此可见，在当前 Bash 中执行的所有命令，都是该 Bash 进程的子进程，而该 Bash 进程，又是 sshd 的子进程。**
