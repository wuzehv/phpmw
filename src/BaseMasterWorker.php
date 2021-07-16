<?php
declare(ticks=1);

namespace PhpMw;

abstract class BaseMasterWorker
{
    private $manager;
    private $master;
    private $pids = [];

    private $workerNum = 8;

    abstract function master();

    abstract function worker($job);

    public function __construct()
    {
        pcntl_signal(SIGTERM, array($this, 'forceKill'));
        pcntl_signal(SIGINT, array($this, 'forceKill'));

        $this->manager = new MwManager();
        $this->master = new MwMaster($this);
    }

    public function forceKill()
    {
        $flag = function_exists('posix_kill');

        foreach ($this->pids as $pid) {
            $flag ? posix_kill($pid, SIGKILL) : exec("kill -KILL $pid");
        }

        // 主进程也退出
        exit;
    }

    public function setWorkerNum($num)
    {
        $this->workerNum = $num;
    }

    public function addJob($job)
    {
        $this->master->addJob($job);
    }

    public function run()
    {
        $this->manager->init();

        $this->startMaster();

        $workerNum = $this->workerNum;
        while ($workerNum-- > 0) {
            $this->startWorker();
        }

        $this->manager->run($this->workerNum);

        // 等待所有子进程退出
        while (pcntl_wait($status) > 0);
    }

    private function startMaster()
    {
        $pid = pcntl_fork();
        if ($pid < 0) {
            exit("fork master fail");
        } elseif ($pid > 0) {
            $this->pids[] = $pid;
        } else {
            $this->master->run($this->manager);
            exit(0);
        }
    }

    private function startWorker()
    {
        $pid = pcntl_fork();
        if ($pid < 0) {
            exit("fork worker fail");
        } elseif ($pid > 0) {
            $this->pids[] = $pid;
        } else {
            $worker = new MwWorker($this);
            $worker->run($this->manager);
            exit(0);
        }
    }
}