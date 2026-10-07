<?php

// Create an empty, separate test schema from the installed schema. Never reset the source.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$root = dirname(__DIR__, 4);
Dotenv\Dotenv::createImmutable($root)->safeLoad();
$source = $_ENV['DB_DATABASE'];
$target = 'govstore_experiment_test_'.date('YmdHis');
if (! preg_match('/^govstore_experiment_test_[0-9]{14}$/', $target) || $source === $target) {
    throw new RuntimeException('Unsafe test database name.');
}
$pdo = new PDO('mysql:host='.$_ENV['DB_HOST'].';port='.($_ENV['DB_PORT'] ?? 3306).';dbname='.$source, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$ddl = [];
foreach ($tables as $table) {
    if (str_starts_with($table, 'gov_experiment_')) {
        continue;
    }
    $ddl[] = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
}
$master = [];
foreach (['gov_geo_areas', 'custom_fields', 'gov_metadata_field_mappings'] as $table) {
    $master[$table] = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
}
$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `'.$target.'`');
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($ddl as $statement) {
    $pdo->exec($statement);
}
foreach ($master as $table => $rows) {
    foreach ($rows as $row) {
        $statement = $pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`', array_keys($row)).'`) VALUES ('.implode(',', array_fill(0, count($row), '?')).')');
        $statement->execute(array_values($row));
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
file_put_contents($root.'/storage/framework/experiment-test-database.txt', $target);
echo "Separate test database created: {$target}\n";
