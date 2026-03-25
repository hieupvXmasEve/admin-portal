<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->guardTestingDatabase($app);

        return $app;
    }

    private function guardTestingDatabase($app): void
    {
        $this->normalizeTestingConnection($app);

        if ($app['config']->has('database.connections.testing')) {
            $app['config']->set('database.default', 'testing');
            $app['db']->setDefaultConnection('testing');
        }

        $defaultConnection = (string) $app['config']->get('database.default');
        $connectionConfig = (array) $app['config']->get("database.connections.{$defaultConnection}", []);

        $driver = (string) ($connectionConfig['driver'] ?? '');
        $database = (string) ($connectionConfig['database'] ?? '');

        if ($driver === 'sqlite' && ($database === ':memory:' || Str::endsWith($database, 'database.sqlite'))) {
            return;
        }

        $protectedDatabases = collect([
            env('DB_DATABASE'),
            $app['config']->get('database.connections.mysql.database'),
            $app['config']->get('database.connections.mariadb.database'),
            $app['config']->get('database.connections.pgsql.database'),
            $app['config']->get('database.connections.sqlsrv.database'),
        ])
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values();

        if ($protectedDatabases->contains($database)) {
            throw new RuntimeException(
                "Unsafe test database configuration detected. Tests are pointing at protected database '{$database}' using connection '{$defaultConnection}'."
            );
        }

        if (! Str::contains(Str::lower($database), 'test')) {
            throw new RuntimeException(
                "Unsafe test database configuration detected. Database '{$database}' does not look like an isolated test database."
            );
        }
    }

    private function normalizeTestingConnection($app): void
    {
        if (! $app['config']->has('database.connections.testing')) {
            return;
        }

        $testing = (array) $app['config']->get('database.connections.testing');
        $primary = (array) $app['config']->get('database.connections.mariadb', []);

        if (($testing['driver'] ?? null) === 'sqlite') {
            return;
        }

        $testingHost = (string) ($testing['host'] ?? '');
        $primaryHost = (string) ($primary['host'] ?? '');

        if (in_array($testingHost, ['localhost', '127.0.0.1', ''], true) && ! in_array($primaryHost, ['localhost', '127.0.0.1', ''], true)) {
            $testing['host'] = $primaryHost;
        }

        foreach (['port', 'username', 'password', 'unix_socket', 'charset', 'collation'] as $key) {
            if (($testing[$key] ?? null) === null || $testing[$key] === '') {
                $testing[$key] = $primary[$key] ?? $testing[$key] ?? null;
            }
        }

        $app['config']->set('database.connections.testing', $testing);
        $app['config']->set('telescope.storage.database.connection', 'testing');

        $database = (string) ($testing['database'] ?? '');
        $host = (string) ($testing['host'] ?? '127.0.0.1');
        $port = (string) ($testing['port'] ?? '3306');
        $username = (string) ($testing['username'] ?? 'root');
        $password = (string) ($testing['password'] ?? '');
        $charset = (string) ($testing['charset'] ?? 'utf8mb4');
        $collation = (string) ($testing['collation'] ?? 'utf8mb4_unicode_ci');

        if ($database === '') {
            throw new RuntimeException('Unsafe test database configuration detected. Testing database name is empty.');
        }

        $pdo = new PDO("mysql:host={$host};port={$port}", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $quotedDatabase = str_replace('`', '``', $database);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$quotedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");
    }
}
