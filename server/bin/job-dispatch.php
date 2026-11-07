#!/usr/bin/env php
<?php

declare(strict_types=1);

use Sandadmin\Job\JobCli;

require dirname(__DIR__) . '/bin/job-cli-autoload.php';

$type = $argv[1] ?? 'noop';
if (!is_string($type) || $type === '') {
    fwrite(STDERR, "usage: php bin/job-dispatch.php [type]\n");
    exit(1);
}

$runtime = JobCli::boot(dirname(__DIR__));
$job = $runtime->dispatcher->dispatch($type, []);
fwrite(STDOUT, $job->id . "\n");
