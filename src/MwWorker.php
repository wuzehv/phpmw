<?php
declare(ticks=1);

namespace PhpMw;

class MwWorker
{
    private $mwObj;
    private $socket;

    public function __construct($mwObj)
    {
        $this->mwObj = $mwObj;
    }

    private function init($ip, $port)
    {
        $this->socket = MwConn::connect($ip, $port);
        MwConn::send($this->socket, 'role', 'worker');
    }

    public function run($manager)
    {
        $this->init($manager->ip, $manager->port);

        while (true) {
            $data = MwConn::read($this->socket);
            if (!$data || $data['type'] === 'quit') {
                break;
            }

            // 捕获异常，避免worker意外退出
            try {
                $this->mwObj->worker($data['data']);
            } catch (\Exception $e) {
                // worker catch exception
            }

            // todo 不处理返回值，触发select
            MwConn::send($this->socket, 'result', 'done');
        }
    }
}