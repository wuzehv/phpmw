<?php
declare(ticks=1);

namespace App\Tasks\Mw;

class MwConn
{
    static function connect($ip, $port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!@socket_connect($socket, $ip, $port)) {
            return false;
        }

        return $socket;
    }

    static function accept($socket)
    {
        $socket = socket_accept($socket);

        return $socket;
    }

    static function send($socket, $type, $data)
    {
        $info = [
            'type' => $type,
            'data' => $data,
        ];

        // 注意换行是必须的，否则会出现问题
        !@socket_write($socket, json_encode($info) . "\n");
    }

    static function read($socket)
    {
        $buf = @socket_read($socket, 102400, PHP_NORMAL_READ);
        if ($buf === false) {
            return false;
        }

        return json_decode($buf, true);
    }

    static function close(&$socket)
    {
        if ($socket) {
            @socket_shutdown($socket);
            socket_close($socket);
            $socket = null;
        }
    }
}