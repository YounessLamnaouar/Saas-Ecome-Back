<?php
chdir(dirname(__DIR__));
$lock = fopen('storage/framework/mail-worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
while (true) {
    foreach ([['queue:work','--stop-when-empty','--max-time=45','--timeout=30','--tries=10','--backoff=300'], ['orders:retry-store-emails']] as $args) {
        $process = proc_open(array_merge([PHP_BINARY, 'artisan'], $args), [0 => ['pipe','r'], 1 => ['file','storage/logs/mail-worker.log','a'], 2 => ['file','storage/logs/mail-worker.log','a']], $pipes);
        if (is_resource($process)) { fclose($pipes[0]); proc_close($process); }
    }
    sleep(15);
}
