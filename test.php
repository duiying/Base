<?php

/**
 * 实现一个进程池，实现原理如下：
 *
 * 1、Master 进程负责创建出几个 Worker 进程，这几个 Worker 进程组成了一个 Worker 进程池
 * 2、Master 进程和 Worker 进程之间通过消息队列通信
 * 3、Master 进程作为 Server 端，另起一个脚本作为 Client，Server 和 Client 通过本地 Socket 进行通信
 * 4、Client 通过 Socket 发送数据给 Server，Server（也就是 Master） 通过消息队列投递给 Worker 处理（投递过程非阻塞）
 * 5、Master 进程一旦收到 SIGINT 信号，此时通过注册的信号处理器，将 $isRunning 改成 false，Master 进程退出事件循环 eventLoop，Master 进程向 Worker 进程发送 exit 数据并回收一系列资源
 */

namespace WorkerPool;

/**
 * Worker 进程
 *
 * @package WorkerPool
 */
class Worker
{
    // 每个 Worker 进程都有一个进程 ID
    public $pid;
    // 每个 Worker 进程都有一个消息队列，和 Master 进程通过消息队列进行 IPC
    public $queue;
}

/**
 * Master 进程
 *
 * @package WorkerPool
 */
class Master
{
    // Worker 进程数量
    public $workerNum       = 3;
    // 是否正在运行
    public $isRunning       = true;
    // 消息队列 key 文件
    public $queueKeyFile    = __FILE__;
    // Worker 进程
    public $workerList      = [];
    // 本地字节流 socket，绑定一个本地文件
    public $socketFile      = __DIR__ . '/' . 'master.sock';
    // socket
    public $socket;
    // 轮转出一个 Worker 进程用
    public $roll            = 0;

    public function __construct($workerNum = 3)
    {
        echo sprintf('Master 进程开始执行了，pid：%d' . PHP_EOL, posix_getpid());

        $this->workerNum = $workerNum;

        // 注册信号处理器
        pcntl_signal(SIGINT, [$this, 'sigHandler']);

        // fork 出几个 Worker 进程
        $this->forkWorker();

        // 监听
        $this->listen();

        // 事件循环
        $this->eventLoop();

        // Master 进程退出了，此时需要：1、关闭创建的 socket；2：通过消息队列向 Worker 进程发送 exit 数据；3：回收子进程；4：删除消息队列；
        socket_close($this->socket);
        foreach ($this->workerList as $k => $v) {
            msg_send($v->queue, 2, 'exit');
        }

        // 被回收的子进程数量
        $childCount = 0;
        while (1) {
            $pid = pcntl_wait($status);
            if ($pid > 0) {
                $childCount++;
            }
            // 所有的子进程都已经被回收
            if ($childCount === $this->workerNum) {
                break;
            }
        }

        foreach ($this->workerList as $k => $v) {
            msg_remove_queue($v->queue);
        }

        echo sprintf('Master 进程结束执行了，pid：%d' . PHP_EOL, posix_getpid());
    }

    /**
     * 信号处理器
     *
     * @param $signo
     */
    public function sigHandler($signo)
    {
        $this->isRunning = false;
    }

    /**
     * fork 出 Worker 进程
     */
    public function forkWorker()
    {
        for ($i = 0; $i < $this->workerNum; $i++) {
            $ipcKey = ftok($this->queueKeyFile, "$i");
            $queue = msg_get_queue($ipcKey);

            $worker = new Worker();
            $worker->queue = $queue;
            // 把 Worker 进程放到父进程的 workerList 中
            $this->workerList[$i] = $worker;

            $worker->pid = pcntl_fork();
            // 子进程
            if ($worker->pid === 0) {
                $this->startWorker($i);
            }
            // 父进程
            else {
                continue;
            }
        }
    }

    /**
     * Worker 进程开始执行
     *
     * @param $i
     */
    public function startWorker($i)
    {
        echo sprintf('Worker 进程开始执行了，pid：%d' . PHP_EOL, posix_getpid());

        $worker = $this->workerList[$i];
        $queue = $worker->queue;

        while (1) {
            msg_receive($queue, 0, $msgType, 1024, $msg);
            if ($msg) {
                if (trim($msg) === 'exit') {
                    break;
                }
                echo sprintf('Worker 进程 pid：%d 收到了数据 data：%s' . PHP_EOL, posix_getpid(), $msg);
            }
        }

        echo sprintf('Worker 进程退出了，pid：%d' . PHP_EOL, posix_getpid());

        exit;
    }

    /**
     * 监听
     */
    public function listen()
    {
        if (file_exists($this->socketFile)) {
            unlink($this->socketFile);
        }
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_bind($this->socket, $this->socketFile);
        socket_listen($this->socket, 10);
    }

    /**
     * Master 进程的事件循环
     */
    public function eventLoop()
    {
        $readSocketList     = [$this->socket];
        $writeSocketList    = [];
        $exceptSocketList   = [];

        echo 'Master 进程开始了事件循环' . PHP_EOL;

        while ($this->isRunning) {
            pcntl_signal_dispatch();

            // IO 多路复用
            $ret = socket_select($readSocketList, $writeSocketList, $exceptSocketList, null, null);

            if ($ret === false) break;
            if ($ret === 0) continue;

            if (!empty($readSocketList)) {
                foreach ($readSocketList as $k => $socket) {
                    if ($socket === $this->socket) {
                        $connectSocket = socket_accept($this->socket);
                        $data = socket_read($connectSocket,1024);
                        if ($data) {
                            $this->sendToWorker($data);
                        }
                        socket_write($connectSocket, 'OK', 2);
                        socket_close($connectSocket);
                    }
                }
            }
        }

        echo 'Master 进程结束了事件循环' . PHP_EOL;
    }

    /**
     * 选出一个 Worker 进程，通过消息队列往该 Worker 进程中发送数据
     *
     * @param $data
     */
    public function sendToWorker($data)
    {
        /** @var Worker $worker */
        $worker = $this->workerList[$this->roll++ % $this->workerNum];
        $queue = $worker->queue;
        // 往消息队列发送一条消息（非阻塞）
        msg_send($queue, 2, $data, true, false);
    }
}

new Master(3);