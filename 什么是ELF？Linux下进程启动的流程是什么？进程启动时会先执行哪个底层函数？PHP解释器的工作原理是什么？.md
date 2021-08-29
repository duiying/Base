# 什么是 ELF？Linux 下进程启动的流程是什么？进程启动时会先执行哪个底层函数？PHP 解释器的工作原理是什么？

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

### 什么是 ELF？

ELF 的英文全称是 The Executable and Linking Format，是 Linux 的主要可执行文件格式。  

ELF 文件的种类主要有 3 种：  

- 可执行文件：Executable File，包含代码和数据，是可以直接运行的程序。其代码和数据都有固定的地址（或相对于基地址的偏移），系统可根据这些地址信息把程序加载到内存执行。（**如 .out 文件**）
- 可重定位文件：Relocatable File，包含基础代码和数据，但它的代码及数据都没有指定绝对地址，因此它适合于与其他目标文件链接来创建可执行文件或者共享目标文件。（**如 .o、.a 文件，其中 .o、.a 文件也被称为静态库文件**）
- 共享目标文件：Shared Object File，也称动态库文件，包含了代码和数据。（**.so 文件，比如 PHP 扩展中常用的动态库文件 redis.so**）



### 总结
