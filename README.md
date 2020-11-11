# php多进程
* master worker模型
* 参考iphp框架实现

### 使用

1. 安装

```bash
composer require wuzehv/phpmw
```

2. 使用

```php
include_once "vendor/autoload.php";

// 继承抽象类
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

进程间通讯采用socket

1. 启动主进程，作为manager，监听随机端口
2. 启动master进程，连接manager
3. 启动多个worker进程，连接manager
4. 调用run方法后，内部调用业务方实现的master方法，添加任务给manager
5. manager将任务分发给空闲的worker进程，worker进程调用业务方实现的worker方法
6. 任务处理完成，所有进程退出

### 可能遇到的问题

#### 僵尸进程

1. master添加完任务后就会退出，在程序退出之前会作为僵尸进程存在
2. worker进程如果出现致命错误，会作为僵尸进程存在，也即不能处理任何请求

最终都会被主进城回收

#### 如何kill所有的进程

直接kill主进程pid即可，因为程序内部做了`SIGINT、SIGTERM`信号处理，信号处理器会系统调用`kill -KILL pid`强制杀死所有子进程

理论上不需要执行类似这种命令`ps -ef | grep ...... | awk '{print $2}' | xargs kill -9`

#### 进程树查看

```bash
htop
ps -fS | grep ......
pstree -p pid
```

#### Too many open files

这个错误是进程文件描述符个数限制，查看设置`ulimit -n`

#### 异常处理

1. master方法内的异常会导致master进程退出，已添加的任务会继续执行，直至退出
2. worker进程内的异常会被捕获，但是不会产生任何错误输出，所以**需要业务方务必自行捕获异常**

#### job少，worker进程多

执行过程中，master进程会迅速执行完毕，`socket_select`获取到退出，当任务分发完成后，manager会调用`quitAll`方法，多于的worker进程会结束，执行中的worker会继续执行，直至完成退出

#### job多，worker进程少

如果job生成非常快，master会迅速写入大量job到`socket`，worker会慢慢进行处理，此时master进程和worker进程会同时存在

在worker进程都在工作的时候，`socket_select`会暂时不监听master进程套接字，直至有worker进程空闲，从而实现进程的分发
