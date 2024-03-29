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
        $this->ip = MwConst::IP;

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

    public function run($workerNum)
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

            if ($this->masterDone && !$this->jobs && count($this->workers) == $workerNum) {
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
                MwConn::send($worker['socket'], MwConst::TYPE_JOB, $job);
            }
        }
    }

    private function dealConn()
    {
        $socket = MwConn::accept($this->socket);

        $data = MwConn::read($socket);

        if ($data['type'] === MwConst::TYPE_ROLE) {
            switch ($data['data']) {
                case MwConst::ROLE_MASTER:
                    $this->master = $socket;
                    break;
                case MwConst::ROLE_WORKER:
                    $this->workers[] = [
                        'working' => false,
                        'socket' => $socket,
                    ];
                    break;
            }

            MwConn::send($socket, MwConst::TYPE_CONN);
        }
    }

    private function dealMaster()
    {
        $res = MwConn::read($this->master);
        if (!$res || $res['type'] === MwConst::TYPE_QUIT) {
            MwConn::close($this->master);
            $this->masterDone = true;
            return;
        }

        if ($res['type'] === MwConst::TYPE_JOB) {
            $this->jobs[] = $res['data'];
        }
    }

    private function dealWorker($socket)
    {
        foreach ($this->workers as $k => $item) {
            if ($socket == $item['socket']) {
                // 丢弃结果，并且如果读取失败，可能是worker已经因为致命错误已经退出
                if (MwConn::read($socket) === false) {
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
            MwConn::send($item['socket'], MwConst::TYPE_QUIT, MwConst::ROLE_WORKER);
        }

        MwConn::close($this->socket);

        MwConn::close($this->master);
    }
}