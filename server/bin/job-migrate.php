#!/usr/bin/env php
<?php

declare(strict_types=1);

use Sandadmin\Job\JobCli;

require dirname(__DIR__) . '/bin/job-cli-autoload.php';

JobCli::boot(dirname(__DIR__));
fwrite(STDOUT, "job_run schema is ready\n");
