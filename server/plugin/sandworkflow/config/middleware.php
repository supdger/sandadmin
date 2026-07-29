<?php

use plugin\saiadmin\app\middleware\CheckAuth;
use plugin\saiadmin\app\middleware\CheckLogin;
use plugin\saiadmin\app\middleware\SystemLog;

return [
    '' => [
        CheckLogin::class,
        CheckAuth::class,
        SystemLog::class,
    ],
];
