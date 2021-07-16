<?php
declare(ticks=1);

namespace PhpMw;

class MwManager
{
    public $ip;
    public $port;

    // 监听套接字
    private $socket;

    // master套接字
    private $master;

    // worker套接字
    private $workers = [];

    // 任务队列
    private $jobs = [];

    // master直接结束后，变为true，结束manager
    private $masterDone = false;

    public function init()
    {
        $this->ip = '127.0.0.1';

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new \Exception(socket_strerror(socket_last_error()));
        }

        // 监听随机端口，方便多个业务同时运行
        if (!@socket_bind($socket, $this->ip)) {
            throw new \Exception(socket_strerror(socket_last_error()));
        }

        if (!@socket_listen($socket)) {
            throw new \Exception(socket_strerror(socket_last_error()));
        }

        socket_getsockname($socket, $addr, $port);

        $this->port = $port;
        $this->socket = $socket;
    }

    public function run()
    {
        while (true) {
            // 分发job给worker进程
            $this->dispatchJob();

            // 组装select套接字
            $read = [
                $this->socket
            ];

            // 如果是任务非常多的情况，这里先不添加master句柄，等待worker处理，空闲出来之后再添加任务
            if ($this->master && !$this->jobs) {
                $read[] = $this->master;
            }

            foreach ($this->workers as $item) {
                $read[] = $item['socket'];
            }

            $write = [];
            $except = [];

            $ret = @socket_select($read, $write, $except, NULL);
            if ($ret === false) {
                break;
            }

            foreach ($read as $iSock) {
                if ($iSock == $this->socket) {
                    $this->dealConn();
                } elseif ($iSock == $this->master) {
                    $this->dealMaster();
                } else {
                    $this->dealWorker($iSock);
                }
            }

            if ($this->masterDone && !$this->jobs) {
                break;
            }
        }

        $this->quitAll();
    }

    private function dispatchJob()
    {
        foreach ($this->workers as $k => $worker) {
            if (!$this->jobs) {
                break;
            }

            if (!$worker['working']) {
                $job = array_shift($this->jobs);
                $this->workers[$k]['working'] = true;
                MwConn::send($worker['socket'], 'job', $job);
            }
        }
    }

    private function dealConn()
    {
        $socket = MwConn::accept($this->socket);

        $data = MwConn::read($socket);

        if ($data['type'] === 'role') {
            if ($data['data'] === 'master') {
                $this->master = $socket;
            }

            if ($data['data'] === 'worker') {
                $this->workers[] = [
                    'working' => false,
                    'socket' => $socket,
                ];
            }
        }
    }

    private function dealMaster()
    {
        $res = MwConn::read($this->master);
        if (!$res || $res['type'] === 'quit') {
            MwConn::close($this->master);
            $this->masterDone = true;
            return;
        }

        if ($res['type'] === 'job') {
            $this->jobs[] = $res['data'];
        }
    }

    private function dealWorker($socket)
    {
        foreach ($this->workers as $k => $item) {
            if ($socket == $item['socket']) {
                // 丢弃结果，并且如果读取失败，可能是worker已经退出，删除套接字
                if (!MwConn::read($socket)) {
                    unset($this->workers[$k]);
                    break;
                }

                // 设置进程结束标识
                $this->workers[$k]['working'] = false;
                break;
            }
        }
    }

    private function quitAll()
    {
        foreach ($this->workers as $item) {
            MwConn::send($item['socket'], 'quit', '');
        }

        MwConn::close($this->socket);

        MwConn::close($this->master);
    }
}