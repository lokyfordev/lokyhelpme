<?php

namespace LokyHelpMe\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class TableInspector
{
    /**
     * @return array<int, string>
     */
    public function getTables(): array
    {
        try {
            $connectionName = (string) config('database.default');
            $schemaConnection = Schema::connection($connectionName);
            $connection = $schemaConnection->getConnection();
            $driver = $connection->getDriverName();

            $tables = match ($driver) {
                'mysql', 'mariadb' => $this->getMysqlTables($connection),
                'pgsql' => $this->getPgsqlTables($connection),
                'sqlite' => $this->getSqliteTables($connection),
                'sqlsrv' => $this->getSqlsrvTables($connection),
                default => $connection->getSchemaBuilder()->getTableListing(),
            };

            return $this->normalizeTables($tables);
        } catch (Throwable $exception) {
            throw new RuntimeException('Database connection failed', 0, $exception);
        }
    }

    /**
     * @param  array<int, mixed>  $tables
     * @return array<int, string>
     */
    protected function normalizeTables(array $tables): array
    {
        return collect($tables)
            ->map(function (mixed $table): ?string {
                if (is_string($table)) {
                    // Some drivers may return schema-qualified names; keep only table part.
                    $parts = explode('.', $table);

                    return (string) end($parts);
                }

                if (is_object($table)) {
                    $values = get_object_vars($table);
                    $value = reset($values);

                    return is_string($value) ? $value : null;
                }

                if (is_array($table)) {
                    $value = reset($table);

                    return is_string($value) ? $value : null;
                }

                return null;
            })
            ->filter(fn (?string $table): bool => is_string($table) && $table !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    protected function getMysqlTables(ConnectionInterface $connection): array
    {
        $database = (string) $connection->getDatabaseName();

        if ($database === '') {
            throw new RuntimeException('Database connection failed');
        }

        return DB::connection($connection->getName())->select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = ?',
            [$database, 'BASE TABLE'],
        );
    }

    /**
     * @return array<int, mixed>
     */
    protected function getPgsqlTables(ConnectionInterface $connection): array
    {
        return DB::connection($connection->getName())->select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = ?',
            ['BASE TABLE'],
        );
    }

    /**
     * @return array<int, mixed>
     */
    protected function getSqliteTables(ConnectionInterface $connection): array
    {
        return DB::connection($connection->getName())->select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
        );
    }

    /**
     * @return array<int, mixed>
     */
    protected function getSqlsrvTables(ConnectionInterface $connection): array
    {
        $database = (string) $connection->getDatabaseName();

        if ($database === '') {
            throw new RuntimeException('Database connection failed');
        }

        return DB::connection($connection->getName())->select(
            'SELECT table_name FROM information_schema.tables WHERE table_catalog = ? AND table_type = ?',
            [$database, 'BASE TABLE'],
        );
    }
}
