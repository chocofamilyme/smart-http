<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

final class Options
{
    const MAX_FAILURES = 5;

    const MAX_RETRIES = 3;

    const INIT_RETRIES = 1;

    const DELAY_RETRY     = 200;

    const TIMEOUT         = 500;

    const CONNECT_TIMEOUT = 1000;
}
