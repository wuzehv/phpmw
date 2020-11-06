# php多进程
* master worker模型
* 参考iphp框架实现

### usage
1. 安装
```bash
composer require wuzehv/phpmw
```
   
2. 使用
```php
include_once "vendor/autoload.php";

class Test extends \PhpMw\BaseMasterWorker
{
    // 添加任务
    function master()
    {
        for ($i = 0; $i < 200; $i++) {
            $this->addJob($i);
        }
    }

    // 处理任务
    function worker($job)
    {
        usleep(100);
        echo $job, PHP_EOL;
    }
}

$obj = new Test();

// 设置进程数，默认8个
$obj->setWorkerNum(5);

$obj->run();
```

### 实现原理
1. 启动主进程，作为manager，监听随机端口
2. 启动master进程，连接manager，并添加任务
3. 启动worker进程，连接manager，等待任务处理，
4. manager接收master添加的任务，分发给空闲worker进程处理
5. 任务处理完成，所有进程退出

所以，如果worker进程设置为8个，那么查看进程的时候会有10个进程（一个主进程，一个master进程）