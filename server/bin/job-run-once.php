#!/usr/bin/env php
<?php

declare(strict_types=1);

use Sandadmin\Job\JobCli;
use Sandadmin\Job\JobHttp;

require dirname(__DIR__) . '/bin/job-cli-autoload.php';

$runtime = JobCli::boot(dirname(__DIR__));
$job = $runtime->executor->tick();
if ($job === null) {
    fwrite(STDOUT, "no pending job\n");
    exit(0);
}

fwrite(STDOUT, json_encode(
    JobHttp::resource($job),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
) . "\n");
exit($job->status->isTerminal() ? 0 : 1);
