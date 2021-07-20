<?php
declare(ticks=1);

namespace PhpMw;

class MwMaster
{
    private $mwObj;
    private $socket;

    public function __construct(BaseMasterWorker $mwObj)
    {
        $this->mwObj = $mwObj;
    }

    private function init($ip, $port)
    {
        $this->socket = MwConn::connect($ip, $port);
        MwConn::send($this->socket, MwConst::TYPE_ROLE, MwConst::ROLE_MASTER);
        $t = MwConn::read($this->socket);
        if (!$t || $t['type'] != MwConst::TYPE_CONN) {
            throw new \Exception("master connect manager error");
        }
    }

    public function run($manager)
    {
        $this->init($manager->ip, $manager->port);

        // master方法如果有异常，会导致master进程终止，最终等待worker任务结束退出
        $this->mwObj->master();

        // 主动发出退出消息
        MwConn::send($this->socket, MwConst::TYPE_QUIT, MwConst::ROLE_MASTER);
    }

    public function addJob($job)
    {
        MwConn::send($this->socket, MwConst::TYPE_JOB, $job);
    }
}