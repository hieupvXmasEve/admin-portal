<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;

trait LoadsSchemaDump
{
    /**
     * Indicates whether schema has been loaded.
     *
     * @var bool
     */
    protected static $schemaLoaded = false;

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function beginDatabaseTransaction()
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                $connection->rollBack();
                $connection->setEventDispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }

    /**
     * Load database schema from dump file.
     *
     * @return void
     */
    protected function loadSchemaDump(): void
    {
        if (static::$schemaLoaded) {
            return;
        }

        $schemaPath = database_path('schema/mariadb-schema.sql');

        if (!file_exists($schemaPath)) {
            // Fallback to migrations if schema dump doesn't exist
            $this->artisan('migrate:fresh', ['--database' => $this->getConnectionName()]);
            static::$schemaLoaded = true;
            return;
        }

        $connection = DB::connection($this->getConnectionName());

        // Disable foreign key checks temporarily
        $connection->statement('SET FOREIGN_KEY_CHECKS=0;');

        // Read and execute schema dump
        $sql = file_get_contents($schemaPath);

        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments

        // Split by semicolon and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($statement) => !empty($statement) && strlen($statement) > 10
        );

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $connection->unprepared($statement);
                } catch (\Exception $e) {
                    // Ignore errors for statements that might already exist
                    // or are not compatible with test environment
                    $errorMessage = $e->getMessage();
                    if (
                        str_contains($errorMessage, 'already exists') === false &&
                        str_contains($errorMessage, 'Duplicate') === false &&
                        str_contains($errorMessage, 'Unknown database') === false
                    ) {
                        // Log unexpected errors in verbose mode
                        if (getenv('TEST_VERBOSE')) {
                            echo "\nSchema dump warning: " . $errorMessage . "\n";
                        }
                    }
                }
            }
        }

        // Re-enable foreign key checks
        $connection->statement('SET FOREIGN_KEY_CHECKS=1;');

        static::$schemaLoaded = true;
    }

    /**
     * Get the database connection name for testing.
     *
     * @return string
     */
    protected function getConnectionName(): string
    {
        return config('database.default', 'testing');
    }

    /**
     * The database connections that should have transactions.
     *
     * @return array
     */
    protected function connectionsToTransact()
    {
        return [$this->getConnectionName()];
    }
}
