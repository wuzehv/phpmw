<?php
declare(ticks=1);

namespace App\Tasks\Mw;

abstract class BaskMasterWorker
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

        while ($this->workerNum-- > 0) {
            $this->startWorker();
        }

        $this->manager->run();

        // 等待所有子进程退出
        while (pcntl_wait($status) > 0);
    }

    public function startMaster()
    {
        $pid = pcntl_fork();
        if ($pid < 0) {

        } elseif ($pid > 0) {
            $this->pids[] = $pid;
        } else {
            $this->master->run($this->manager);
            exit(0);
        }
    }

    public function startWorker()
    {
        $pid = pcntl_fork();
        if ($pid < 0) {

        } elseif ($pid > 0) {
            $this->pids[] = $pid;
        } else {
            $worker = new MwWorker($this);
            $worker->run($this->manager);
            exit(0);
        }
    }
}