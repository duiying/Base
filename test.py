import socket
import sys

server = socket.socket(socket.AF_INET,socket.SOCK_STREAM)

host = socket.gethostname()

port = 9512

server.bind(('0.0.0.0',port))

server.listen(5)

while True:
    client,addr = server.accept()

    msg = client.recv(1024)
    print(msg)
    print(str(addr))
    msg = "HTTP/1.1 OK 200\r\nContent-Type: text/html\r\nContent-Length: 11\r\n\r\nhello,world"
    client.send(msg.encode('utf-8'))

    client.close()