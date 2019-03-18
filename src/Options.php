<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

final class Options
{
    const MAX_FAILURES = 20;

    const MAX_RETRIES = 3;

    const INIT_RETRIES = 1;

    //milliseconds
    const DELAY_RETRY = 200;

    //seconds
    const TIMEOUT = 0.5;

    //seconds
    const LOCK_TIME = 600;

    //seconds
    const CONNECT_TIMEOUT = 1;
}
