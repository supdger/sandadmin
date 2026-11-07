#!/usr/bin/env php
<?php

declare(strict_types=1);

use Sandadmin\Job\JobCli;
use Sandadmin\Job\JobHttp;

require dirname(__DIR__) . '/bin/job-cli-autoload.php';

$id = $argv[1] ?? '';
if (!is_string($id) || $id === '') {
    fwrite(STDERR, "usage: php bin/job-show.php <id>\n");
    exit(1);
}

$runtime = JobCli::boot(dirname(__DIR__));
$outcome = JobHttp::show($runtime->store, $id, 'cli');
fwrite(STDOUT, json_encode(
    $outcome['body'],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
) . "\n");
exit($outcome['status'] === 200 ? 0 : 1);
