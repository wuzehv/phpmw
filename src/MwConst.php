<?php
declare(ticks=1);

namespace PhpMw;

class MwConst
{
    const IP = '127.0.0.1';

    const TYPE_ROLE = 'role';
    const TYPE_JOB  = 'job';
    const TYPE_QUIT = 'quit';
    const TYPE_CONN = 'conn';
    const TYPE_DONE = 'result';

    const ROLE_MASTER = 'master';
    const ROLE_WORKER = 'worker';
}