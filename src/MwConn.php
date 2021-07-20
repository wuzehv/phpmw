<?php
declare(ticks=1);

namespace PhpMw;

class MwConn
{
    static function connect($ip, $port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new \Exception(socket_strerror(socket_last_error()));
        }

        if (!@socket_connect($socket, $ip, $port)) {
            throw new \Exception(socket_strerror(socket_last_error()));
        }

        return $socket;
    }

    static function accept($socket)
    {
        $socket = socket_accept($socket);

        return $socket;
    }

    static function send($socket, $type, $data = '')
    {
        $info = [
            'type' => $type,
            'data' => $data,
        ];

        // 注意换行是必须的，否则会出现问题
        return @socket_write($socket, json_encode($info) . "\n");
    }

    static function read($socket)
    {
        $res = '';
        while (true) {
            $buf = @socket_read($socket, 1024 * 10, PHP_NORMAL_READ);
            if ($buf === false) {
                return false;
            }

            $res .= $buf;
            if (strpos($buf, "\n") !== false) {
                break;
            }
        }

        return json_decode(substr($res, 0, -1), true);
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