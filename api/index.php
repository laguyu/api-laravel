<?php

try {
    require __DIR__.'/../vendor/autoload.php';

    if (! defined('LARAVEL_START')) {
        define('LARAVEL_START', microtime(true));
    }

    $setEnv = static function (string $key, string $value): void {
        $_ENV[$key] = $value;
        putenv($key.'='.$value);
    };

    // Prevent Symfony from treating /api as script base path in serverless.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';

    // Writable/runtime-safe settings for Vercel.
    $setEnv('LOG_CHANNEL', 'stderr');
    $setEnv('LOG_STACK', 'stderr');
    $setEnv('FORCE_JSON_RESPONSE', 'true');
    $setEnv('APP_ROUTES_CACHE', '/tmp/routes.php');
    $setEnv('APP_PACKAGES_CACHE', '/tmp/packages.php');
    $setEnv('APP_SERVICES_CACHE', '/tmp/services.php');
    $setEnv('APP_CONFIG_CACHE', '/tmp/config.php');
    $setEnv('APP_EVENTS_CACHE', '/tmp/events.php');
    $setEnv('CACHE_STORE', 'array');
    $setEnv('CACHE_DRIVER', 'array');

    if (file_exists('/tmp/routes.php')) {
        @unlink('/tmp/routes.php');
    }

    $dbConnection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? null);

    if ($dbConnection === 'mysql' && empty(getenv('MYSQL_ATTR_SSL_CA') ?: ($_ENV['MYSQL_ATTR_SSL_CA'] ?? null))) {
        foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem', '/etc/ssl/certs/ca-bundle.crt'] as $caPath) {
            if (file_exists($caPath)) {
                $setEnv('MYSQL_ATTR_SSL_CA', $caPath);
                break;
            }
        }
    }

    $shouldBootstrap = false;

    // SQLite fallback with self-healing DB in /tmp.
    if (empty($dbConnection) || $dbConnection === 'sqlite') {
        $setEnv('DB_CONNECTION', 'sqlite');
        $setEnv('DB_DATABASE', '/tmp/database.sqlite');

        if (! file_exists('/tmp/database.sqlite')) {
            touch('/tmp/database.sqlite');
            $shouldBootstrap = true;
        }
    }

    // MySQL/TiDB: always verify schema health.
    if (! empty($dbConnection) && $dbConnection !== 'sqlite') {
        $shouldBootstrap = true;
    }

    /** @var \Illuminate\Foundation\Application|null $app */
    $app = null;

    if ($shouldBootstrap) {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->singleton(
            Illuminate\Contracts\Foundation\MaintenanceMode::class,
            fn () => new Illuminate\Foundation\FileBasedMaintenanceMode()
        );

        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $schema = Illuminate\Support\Facades\DB::connection()->getSchemaBuilder();
        $hasMigrations = $schema->hasTable('migrations');
        $hasProducts = $schema->hasTable('products');

        if (! $hasMigrations || ! $hasProducts) {
            $migrateExitCode = $kernel->call('migrate', ['--force' => true]);
            $migrateOutput = trim($kernel->output());

            if ($migrateExitCode !== 0) {
                throw new RuntimeException('migrate failed: '.$migrateOutput);
            }

            $seedExitCode = $kernel->call('db:seed', ['--force' => true]);
            $seedOutput = trim($kernel->output());

            if ($seedExitCode !== 0) {
                throw new RuntimeException('db:seed failed: '.$seedOutput);
            }

            if (! $schema->hasTable('products')) {
                throw new RuntimeException('bootstrap completed without creating products table.');
            }
        } elseif ($schema->hasTable('users')) {
            $usersCount = (int) Illuminate\Support\Facades\DB::table('users')->count();

            if ($usersCount === 0) {
                $seedExitCode = $kernel->call('db:seed', ['--force' => true]);
                $seedOutput = trim($kernel->output());

                if ($seedExitCode !== 0) {
                    throw new RuntimeException('db:seed failed: '.$seedOutput);
                }
            }
        }
    }

    if (! $app) {
        $app = require __DIR__.'/../bootstrap/app.php';
    }

    // Defensive bindings for serverless cold starts.
    $app->instance('routes.cached', false);
    $app->singleton(
        Illuminate\Contracts\Foundation\MaintenanceMode::class,
        fn () => new Illuminate\Foundation\FileBasedMaintenanceMode()
    );

    if (! $app->bound('files')) {
        $app->instance('files', new Illuminate\Filesystem\Filesystem());
    }
    if (! $app->bound('view')) {
        $app->register(Illuminate\View\ViewServiceProvider::class);
    }
    if (! $app->bound(App\Repositories\Contracts\ProductRepositoryInterface::class)) {
        $app->register(App\Providers\RepositoryServiceProvider::class);
    }
    if (! $app->bound('cache')) {
        $app->register(Illuminate\Cache\CacheServiceProvider::class);
    }
    if (! $app->bound('db')) {
        $app->register(Illuminate\Database\DatabaseServiceProvider::class);
    }
    if ($app->bound('db')) {
        Illuminate\Database\Eloquent\Model::setConnectionResolver($app->make('db'));
    }

    // Safety net: load route file if product routes are absent.
    $routes = $app['router']->getRoutes()->getRoutesByMethod()['GET'] ?? [];
    $hasProductsRoute = false;

    foreach ($routes as $route) {
        $uri = $route->uri();
        if ($uri === 'v1/products' || $uri === 'api/v1/products') {
            $hasProductsRoute = true;
            break;
        }
    }

    if (! $hasProductsRoute) {
        require __DIR__.'/../routes/api.php';
    }

    $app->handleRequest(Illuminate\Http\Request::capture());
} catch (\Throwable $e) {
    $debug = filter_var(getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? false), FILTER_VALIDATE_BOOLEAN);
    $message = $debug ? $e->getMessage() : 'Internal Server Error';

    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');

    $payload = [
        'success' => false,
        'message' => $message,
    ];

    if ($debug) {
        $payload['error'] = [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice($e->getTrace(), 0, 8),
        ];
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
