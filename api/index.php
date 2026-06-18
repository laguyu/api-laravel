<?php

$dbConnection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? null);

// If database connection is not set, or is explicitly set to sqlite,
// we fall back to a self-healing local SQLite in /tmp
if (empty($dbConnection) || $dbConnection === 'sqlite') {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['DB_DATABASE'] = '/tmp/database.sqlite';
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=/tmp/database.sqlite');

    $dbPath = '/tmp/database.sqlite';
    if (!file_exists($dbPath)) {
        touch($dbPath);
        
        // Bootstrap the Laravel application to run migrations programmatically
        $app = require __DIR__ . '/../bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        
        // Run migrations and seed database
        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', ['--force' => true]);
    }
}

// Forward the request to Laravel's public entrypoint
require __DIR__ . '/../public/index.php';
