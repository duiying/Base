# 什么是 ELF？Linux 下进程启动的流程是什么？PHP、Go、Python 进程启动时会先执行哪个底层函数？PHP 解释器的执行过程是怎样的？

### 环境准备

1、安装 PHP  

2、安装 Go  

```sh
# 下载 & 解压
wget https://studygolang.com/dl/golang/go1.17.linux-amd64.tar.gz
tar -xvf go1.17.linux-amd64.tar.gz -C /home/work/service

# vim /etc/profile
export GOROOT=/home/work/service/go
export GOPATH=/home/work/gopath
export PATH=$PATH:$GOROOT/bin:$GOPATH/bin
source /etc/profile

# 创建目录
mkdir -p /home/work/gopath
```

3、Python3 系统自带，无需安装  

4、分别创建 PHP、Go、Python3 的 Hello World 源文件：  

```php
<?php
echo 'Hello World';
```

```go
package main

import "fmt"

func main() {  
    fmt.Println("Hello World")
}
```

```Python3
print('Hello World')
```

```sh
[work@localhost hello]$ ls
hello.go  hello.php  hello.py
```

### 什么是 ELF？

ELF 的英文全称是 The Executable and Linking Format，是 Linux 的主要可执行文件格式。  

ELF 文件的种类主要有 3 种：  

- 可执行文件：Executable File，包含代码和数据，是可以直接运行的程序。其代码和数据都有固定的地址（或相对于基地址的偏移），系统可根据这些地址信息把程序加载到内存执行。（**如 .out 文件**）
- 可重定位文件：Relocatable File，包含基础代码和数据，但它的代码及数据都没有指定绝对地址，因此它适合于与其他目标文件链接来创建可执行文件或者共享目标文件。（**如 .o、.a 文件，其中 .o、.a 文件也被称为静态库文件**）
- 共享目标文件：Shared Object File，也称动态库文件，包含了代码和数据。（**.so 文件，比如 PHP 扩展中常用的动态库文件 redis.so**）

我们可以用 `file` 命令查看 PHP、Go、Python3 的文件类型：  

```sh
[work@localhost hello]$ whereis php
php: /home/work/service/php74/bin/php
[work@localhost hello]$ file /home/work/service/php74/bin/php
/home/work/service/php74/bin/php: ELF 64-bit LSB executable, x86-64, version 1 (SYSV), dynamically linked (uses shared libs), for GNU/Linux 2.6.32, BuildID[sha1]=be70f4055384634062f190c18c9357eec1d5d30e, not stripped
[work@localhost hello]$ whereis go
go: /home/work/service/go/bin/go
[work@localhost hello]$ file /home/work/service/go/bin/go
/home/work/service/go/bin/go: ELF 64-bit LSB executable, x86-64, version 1 (SYSV), dynamically linked (uses shared libs), not stripped
[work@localhost hello]$ whereis python3
python3: /usr/bin/python3 /usr/bin/python3.6 /usr/bin/python3.6m /usr/lib/python3.6 /usr/lib64/python3.6 /usr/include/python3.6m /usr/share/man/man1/python3.1.gz
[work@localhost hello]$ file /usr/bin/python3.6m
/usr/bin/python3.6m: ELF 64-bit LSB executable, x86-64, version 1 (SYSV), dynamically linked (uses shared libs), for GNU/Linux 2.6.32, BuildID[sha1]=96fc79162d4f6a1922356d5166542f79f7737f92, stripped
```

可以看出它们都是 ELF 文件。  

我们也可以看下源码文件的类型：  

```sh
[work@localhost hello]$ file hello.php
hello.php: PHP script, ASCII text
[work@localhost hello]$ file hello.go
hello.go: C source, ASCII text
[work@localhost hello]$ file hello.py
hello.py: ASCII text
```

可以看出，**源码文件只是普通的文本文件**。  

我们可以用 `ldd` 命令查看 PHP、Go、Python3 依赖的库。  

```sh
[work@localhost hello]$ ldd /home/work/service/php74/bin/php
	linux-vdso.so.1 =>  (0x00007fff4b4ab000)
	libcrypt.so.1 => /lib64/libcrypt.so.1 (0x00007f6991fb9000)
	libresolv.so.2 => /lib64/libresolv.so.2 (0x00007f6991d9f000)
	librt.so.1 => /lib64/librt.so.1 (0x00007f6991b97000)
	libm.so.6 => /lib64/libm.so.6 (0x00007f6991895000)
	libdl.so.2 => /lib64/libdl.so.2 (0x00007f6991691000)
	libxml2.so.2 => /lib64/libxml2.so.2 (0x00007f6991327000)
	libssl.so.10 => /lib64/libssl.so.10 (0x00007f69910b5000)
	libcrypto.so.10 => /lib64/libcrypto.so.10 (0x00007f6990c52000)
	libsqlite3.so.0 => /lib64/libsqlite3.so.0 (0x00007f699099d000)
	libz.so.1 => /lib64/libz.so.1 (0x00007f6990787000)
	libcurl.so.4 => /lib64/libcurl.so.4 (0x00007f699051d000)
	libpng15.so.15 => /lib64/libpng15.so.15 (0x00007f69902f2000)
	libjpeg.so.62 => /lib64/libjpeg.so.62 (0x00007f699009d000)
	libfreetype.so.6 => /lib64/libfreetype.so.6 (0x00007f698fdde000)
	libonig.so.105 => /lib64/libonig.so.105 (0x00007f698fb4c000)
	libc.so.6 => /lib64/libc.so.6 (0x00007f698f77e000)
	libfreebl3.so => /lib64/libfreebl3.so (0x00007f698f57b000)
	libpthread.so.0 => /lib64/libpthread.so.0 (0x00007f698f35f000)
	/lib64/ld-linux-x86-64.so.2 (0x00007f69921f0000)
	liblzma.so.5 => /lib64/liblzma.so.5 (0x00007f698f139000)
	libgssapi_krb5.so.2 => /lib64/libgssapi_krb5.so.2 (0x00007f698eeec000)
	libkrb5.so.3 => /lib64/libkrb5.so.3 (0x00007f698ec03000)
	libcom_err.so.2 => /lib64/libcom_err.so.2 (0x00007f698e9ff000)
	libk5crypto.so.3 => /lib64/libk5crypto.so.3 (0x00007f698e7cc000)
	libidn.so.11 => /lib64/libidn.so.11 (0x00007f698e599000)
	libssh2.so.1 => /lib64/libssh2.so.1 (0x00007f698e36c000)
	libssl3.so => /lib64/libssl3.so (0x00007f698e113000)
	libsmime3.so => /lib64/libsmime3.so (0x00007f698deeb000)
	libnss3.so => /lib64/libnss3.so (0x00007f698dbbc000)
	libnssutil3.so => /lib64/libnssutil3.so (0x00007f698d98c000)
	libplds4.so => /lib64/libplds4.so (0x00007f698d788000)
	libplc4.so => /lib64/libplc4.so (0x00007f698d583000)
	libnspr4.so => /lib64/libnspr4.so (0x00007f698d345000)
	liblber-2.4.so.2 => /lib64/liblber-2.4.so.2 (0x00007f698d136000)
	libldap-2.4.so.2 => /lib64/libldap-2.4.so.2 (0x00007f698cee1000)
	libbz2.so.1 => /lib64/libbz2.so.1 (0x00007f698ccd1000)
	libkrb5support.so.0 => /lib64/libkrb5support.so.0 (0x00007f698cac1000)
	libkeyutils.so.1 => /lib64/libkeyutils.so.1 (0x00007f698c8bd000)
	libsasl2.so.3 => /lib64/libsasl2.so.3 (0x00007f698c6a0000)
	libselinux.so.1 => /lib64/libselinux.so.1 (0x00007f698c479000)
	libpcre.so.1 => /lib64/libpcre.so.1 (0x00007f698c217000)

[work@localhost hello]$ ldd /home/work/service/go/bin/go
	linux-vdso.so.1 =>  (0x00007fff33be7000)
	libpthread.so.0 => /lib64/libpthread.so.0 (0x00007f06b22d5000)
	libc.so.6 => /lib64/libc.so.6 (0x00007f06b1f07000)
	/lib64/ld-linux-x86-64.so.2 (0x00007f06b24f1000)

[work@localhost hello]$ ldd /usr/bin/python3.6m
	linux-vdso.so.1 =>  (0x00007ffd6af30000)
	libpython3.6m.so.1.0 => /lib64/libpython3.6m.so.1.0 (0x00007ff1ee181000)
	libpthread.so.0 => /lib64/libpthread.so.0 (0x00007ff1edf65000)
	libdl.so.2 => /lib64/libdl.so.2 (0x00007ff1edd61000)
	libutil.so.1 => /lib64/libutil.so.1 (0x00007ff1edb5e000)
	libm.so.6 => /lib64/libm.so.6 (0x00007ff1ed85c000)
	libc.so.6 => /lib64/libc.so.6 (0x00007ff1ed48e000)
	/lib64/ld-linux-x86-64.so.2 (0x00007ff1ee6a9000)
```

可以看到 PHP、Go、Python3 都依赖：  

- ld-linux.so.2 是 Linux 下的动态库加载器 / 链接器
- libc：glibc 是 GNU 发布的 libc 库，即 c 运行库。 glibc 是 Linux 系统中最底层的 API，几乎其它任何运行库都会依赖于 glibc

这些 .so 是动态库文件，当我们运行 go、php、python3 命令的时候会加载这些库，然后可以使用这些库提供的库函数，这些 .so 文件也是 ELF 文件的一种，如下：  

```sh
[work@localhost hello]$ file /lib64/libc.so.6
/lib64/libc.so.6: symbolic link to `libc-2.17.so'
[work@localhost hello]$ file /lib64/libc-2.17.so
/lib64/libc-2.17.so: ELF 64-bit LSB shared object, x86-64, version 1 (GNU/Linux), dynamically linked (uses shared libs), BuildID[sha1]=f9fafde281e0e0e2af45911ad0fa115b64c2cea8, for GNU/Linux 2.6.32, not stripped
```

我们还可以通过 `readelf` 命令查看 ELF 文件的信息。  

```sh
[work@localhost hello]$ readelf /home/work/service/php74/bin/php -h
ELF Header:
  Magic:   7f 45 4c 46 02 01 01 00 00 00 00 00 00 00 00 00
  Class:                             ELF64
  Data:                              2's complement, little endian
  Version:                           1 (current)
  OS/ABI:                            UNIX - System V
  ABI Version:                       0
  Type:                              EXEC (Executable file)
  Machine:                           Advanced Micro Devices X86-64
  Version:                           0x1
  Entry point address:               0x4542e7
  Start of program headers:          64 (bytes into file)
  Start of section headers:          41124888 (bytes into file)
  Flags:                             0x0
  Size of this header:               64 (bytes)
  Size of program headers:           56 (bytes)
  Number of program headers:         9
  Size of section headers:           64 (bytes)
  Number of section headers:         38
  Section header string table index: 37
```

我们可以通过 `nm` 命令查看这些库提供了哪些函数。（比如 libc 库提供了 execve 函数）  

```sh
[work@localhost hello]$ nm /lib64/libc-2.17.so | grep execve
00000000000c5c30 W execve
00000000000c5c30 t __execve
00000000000c5c60 T fexecve
```

### Linux 是如何启动一个进程的？

**PHP**：  

```sh
[work@localhost hello]$ strace -f -s 65535 -o php_strace.log php hello.php
Hello World[work@localhost hello]$ vim php_strace.log
```

查看 `php_strace.log` 内容，发现下面两行内容：  

```
# php_strace.log 第 1 行内容
85333 execve("/home/work/service/php74/bin/php", ["php", "hello.php"], 0x7ffd100d11e0 /* 22 vars */) = 0
# php_strace.log 第 125 行内容
85333 open("/lib64/libc.so.6", O_RDONLY|O_CLOEXEC) = 3
```

我们可以看到执行命令后，会加载 libc 库，调用该库提供的 execve 函数，函数原型：  

```c
int execve(const char *path, char *const argv[], char *const envp[]);
```

execve() 是执行程序函数，我们可以用 `man` 命令查看 execve 函数的介绍。  

```sh
[work@localhost hello]$ man execve
execve() executes the program pointed to by filename.
```

原来是根据文件 filename 执行文件，并且把 argv 当做参数传递给它，这样我们就知道原来执行 php、go、python3 命令时，它们会通过 execve 这个函数去加载执行我们写的代码。  

Go、Python3 同理，它们也加载了 libc 库，并在入口调用了 execve 函数，可以通过 strace 日志看到：  

```sh
strace -f -s 65535 -o go_strace.log go run hello.go
strace -f -s 65535 -o python3_strace.log python3 hello.py
```

### PHP 解释器的执行过程是怎样的？

`php hello.php` 执行过程如下：  

<div align=center><img src="https://raw.githubusercontent.com/duiying/img/master/PHP解释器.png" width="400"></div>  

> 看完上面的演示，我相信大家应该有一个认识，虽然语言写法不同，但是它们的系统调用都是一样的，大家应该往深的方向看，不要局限于表面的编程语言，脚本语言。

### 总结

- **什么是 ELF？它有哪些类型的文件？**（ELF 是Linux 下的主要可执行文件格式，分为三种：可执行文件、可重定位文件、共享目标文件。）
- **用什么命令查看文件类型？**（file）
- **用什么命令查看程序依赖的库？**（ldd）
- **各个语言通用的库有哪些？**（libc、ld-linux 等）
- **怎么查看 ELF 文件的信息？**（readelf）
- **怎么查看动态库提供了哪些函数？**（nm）
- **PHP 解释器的工作流程是怎样的？Linux 下启动进程的过程是什么？**（调用内核 execve 函数，将文件名传给 PHP 解释器，PHP 解释器读取并执行该文件，进程退出）
- **PHP、Go、Python 程序启动时先运行哪个底层函数？**（execve）
- **hello.php 是 ELF 文件还是 ASCII text 文件？**（ASCII text 文件）
