<?php
// job_runner.php

$jobName = $argv[1] ?? null;
$jobsDir = __DIR__ . "/jobs";

// load available jobs dynamically
$jobs = [];
foreach (glob($jobsDir . "/*.php") as $file) {
    $name = basename($file, ".php");
    $jobs[$name] = require $file;
}

// usage info
if (!$jobName || !isset($jobs[$jobName])) {
    echo "Usage: php job_runner.php [" . implode("|", array_keys($jobs)) . "]\n";
    exit(1);
}

// run job
$jobs[$jobName]();
echo "✅ Job '$jobName' executed successfully!\n";
