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
        MwConn::send($this->socket, MwConst::TYPE_ROLE, MwConst::ROLE_WORKER);
        $t = MwConn::read($this->socket);
        if (!$t || $t['type'] != MwConst::TYPE_CONN) {
            throw new \Exception("worker connect manager error");
        }
    }

    public function run($manager)
    {
        $this->init($manager->ip, $manager->port);

        while (true) {
            $data = MwConn::read($this->socket);
            if (!$data || $data['type'] === MwConst::TYPE_QUIT) {
                break;
            }

            // 捕获异常，避免worker意外退出
            try {
                // 不处理返回值
                $this->mwObj->worker($data['data']);
            } catch (\Throwable $e) {
                // worker catch exception
            }

            // 触发select
            MwConn::send($this->socket, MwConst::TYPE_DONE);
        }
    }
}