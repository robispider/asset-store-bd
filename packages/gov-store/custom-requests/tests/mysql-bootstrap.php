<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

function customRequestsMysqlApplication()
{
    $root = dirname(__DIR__, 4);
    require_once $root.'/vendor/autoload.php';
    $marker = $root.'/storage/framework/experiment-test-database.txt';
    $database = is_file($marker) ? trim(file_get_contents($marker)) : '';
    if (! preg_match('/^govstore_experiment_test_[0-9]{14}$/', $database)) {
        throw new RuntimeException('Prepare a separate schema clone before running the MySQL checks.');
    }
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (DB::connection()->getDatabaseName() !== $database || $database === 'snipeit') {
        throw new RuntimeException('Refusing to run MySQL checks outside the separate schema.');
    }
    config(['govstore-access.mode' => 'enforce', 'logging.default' => 'single', 'logging.channels.single.path' => storage_path('framework/custom-requests-mysql-test.log')]);

    return $app;
}
