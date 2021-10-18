package main

import (
        "fmt"
        "syscall"
        "io/ioutil"
)

func main(){
        // 创建 socket
        fd,err:=syscall.Socket(syscall.AF_INET,syscall.SOCK_STREAM,0)
        if err!=nil{
                fmt.Println("socket 创建失败")
                return
        }

        var address syscall.SockaddrInet4
        address.Port=9511
        address.Addr=[4]byte{0,0,0,0}
        // 绑定
        err = syscall.Bind(fd,&address)
        if err!=nil{
                fmt.Println("bind 失败")
                return
        }
        // 监听
        err = syscall.Listen(fd,1024)
        if err!=nil{
                fmt.Println("listen 失败")
                return
        }

        connfd,clientAddr,err:=syscall.Accept(fd)
        if err!=nil{
                fmt.Println("accept 失败",err)
        }

        fmt.Println("客户端信息：",clientAddr)

        // 接收客户端的数据
        var msg [1024]byte
        data:=msg[:]
        nReadBytes,err:=syscall.Read(connfd,data)
        if err!=nil{
                fmt.Println("read 失败",err)
        }

        fmt.Println(nReadBytes,string(data))

        // 读取文件的内容并封装为 HTTP 报文，同时响应给客户端，然后关闭客户端的连接
        content,err:=ioutil.ReadFile("./index.html")
        if err!=nil{
                fmt.Printf("read error")
        }
        var resp string
        resp = fmt.Sprintf("HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: %d\r\n\r\n%v",len(string(content)),string(content))

        nWriteByte,err:=syscall.Write(connfd,[]byte(resp))
        if err!=nil{
                fmt.Println("write 失败",err)
        }

        fmt.Printf("write %d bytes\r\n",nWriteByte)
        syscall.Close(connfd)
}