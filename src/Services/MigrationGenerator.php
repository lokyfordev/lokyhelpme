<?php

namespace LokyHelpMe\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class MigrationGenerator
{
    protected TableInspector $tableInspector;

    protected InputValidator $validator;

    protected CommandPreview $preview;

    public function __construct(protected Command $command, ?TableInspector $tableInspector = null)
    {
        $this->tableInspector = $tableInspector ?? new TableInspector();
        $this->validator = new InputValidator();
        $this->preview = new CommandPreview($command);
    }

    public function run(): int
    {
        $migrationType = $this->command->choice(
            'Migration type',
            ['Create new table', 'Create model with migration', 'Modify existing table'],
            'Create new table',
        );

        return match ($migrationType) {
            'Create new table' => $this->createTable(),
            'Create model with migration' => $this->createModelMigration(),
            'Modify existing table' => $this->modifyTable(),
            default => SymfonyCommand::FAILURE,
        };
    }

    public function createTable(): int
    {
        $table = $this->askTableName('Table name?');
        $columns = $this->askColumns();
        $migrationName = sprintf('create_%s_table', $table);

        $exitCode = $this->preview->previewAndRun('make:migration', [
            'name' => $migrationName,
            '--create' => $table,
        ]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode !== SymfonyCommand::SUCCESS) {
            $this->command->error('Migration generation failed.');

            return $exitCode;
        }

        $this->persistColumnsInMigrationFile($migrationName, $columns);

        $this->command->info('Migration created successfully.');
        $this->logAction(sprintf('User created migration: %s', $migrationName));

        return $exitCode;
    }

    public function createModelMigration(): int
    {
        $model = $this->askClassName('Model name?');
        $columns = $this->askColumns();

        $exitCode = $this->preview->previewAndRun('make:model', [
            'name' => $model,
            '-m' => true,
        ]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode !== SymfonyCommand::SUCCESS) {
            $this->command->error('Model migration generation failed.');

            return $exitCode;
        }

        $table = Str::snake(Str::pluralStudly($model));
        $migrationName = sprintf('create_%s_table', $table);
        $this->persistColumnsInMigrationFile($migrationName, $columns);

        $this->command->info('Model and migration created successfully.');
        $this->logAction(sprintf('User generated model with migration: %s', $model));

        return $exitCode;
    }

    public function modifyTable(): int
    {
        try {
            $tables = $this->tableInspector->getTables();
        } catch (RuntimeException) {
            $this->command->error('Database connection failed.');
            $this->command->error('Check your .env configuration.');

            return SymfonyCommand::FAILURE;
        }

        if ($tables === []) {
            $this->command->warn('No tables found in the current database.');

            return SymfonyCommand::FAILURE;
        }

        $table = $this->command->choice('Which table do you want to modify?', $tables, $tables[0]);
        $columns = $this->askColumns();
        $migrationName = sprintf('update_%s_table', $table);

        $exitCode = $this->preview->previewAndRun('make:migration', [
            'name' => $migrationName,
            '--table' => $table,
        ]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode !== SymfonyCommand::SUCCESS) {
            $this->command->error('Migration generation failed.');

            return $exitCode;
        }

        $this->persistColumnsInMigrationFile($migrationName, $columns);

        $this->command->info(sprintf('Migration created for table: %s', $table));
        $this->logAction(sprintf('User modified table: %s', $table));

        return $exitCode;
    }

    public function generateSeeder(): int
    {
        $name = $this->ensureSeederSuffix($this->askClassName('Seeder name?'));

        $exitCode = $this->preview->previewAndRun('make:seeder', ['name' => $name]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Seeder created successfully.');
            $this->logAction(sprintf('User generated seeder: %s', $name));
        } else {
            $this->command->error('Seeder generation failed.');
        }

        return $exitCode;
    }

    /**
     * @return array<int, string>
     */
    protected function askColumns(): array
    {
        $this->command->line('Type "end" when finished adding columns.');
        $columns = [];

        do {
            $columnName = trim((string) $this->command->ask('Column name? (type "end" to finish)'));

            if (strtolower($columnName) === 'end') {
                break;
            }

            if (! $this->validator->validateNotEmpty($columnName)) {
                $this->command->error('Invalid input.');
                continue;
            }

            if (! $this->validator->validateColumnName($columnName)) {
                $this->command->error('Invalid column name format');
                continue;
            }

            $columns[] = $this->buildColumnDefinition($columnName);
        } while (true);

        return $columns;
    }

    protected function buildColumnDefinition(string $columnName): string
    {
        $columnType = $this->command->choice(
            'Column type?',
            ['string', 'text', 'integer', 'bigInteger', 'boolean', 'decimal', 'float', 'date', 'dateTime', 'timestamp', 'enum', 'json', 'uuid'],
            'string',
        );

        $arguments = [var_export($columnName, true)];

        if ($columnType === 'string') {
            $length = trim((string) $this->command->ask('String length? (optional, default 255)'));

            if ($length !== '' && ctype_digit($length)) {
                $arguments[] = $length;
            }
        }

        if ($columnType === 'enum') {
            $enumValues = $this->askEnumValues();
            $arguments[] = '[' . implode(', ', array_map(static fn (string $value): string => var_export($value, true), $enumValues)) . ']';
        }

        if ($columnType === 'decimal' || $columnType === 'float') {
            $precision = trim((string) $this->command->ask('Precision? (default 8)'));
            $scale = trim((string) $this->command->ask('Scale? (default 2)'));
            $arguments[] = ctype_digit($precision) ? $precision : '8';
            $arguments[] = ctype_digit($scale) ? $scale : '2';
        }

        $line = sprintf('$table->%s(%s)', $columnType, implode(', ', $arguments));

        if ($this->command->confirm('Nullable?', false)) {
            $line .= '->nullable()';
        }

        if ($this->command->confirm('Unique?', false)) {
            $line .= '->unique()';
        }

        $defaultValue = (string) $this->command->ask('Default value? (leave empty for none)');

        if ($defaultValue !== '') {
            $line .= sprintf('->default(%s)', $this->formatDefaultValue($defaultValue, $columnType));
        }

        return $line . ';';
    }

    /**
     * @return array<int, string>
     */
    protected function askEnumValues(): array
    {
        do {
            $this->command->line('Enum values? Use commas or spaces (example: admin,superadmin).');
            $rawValue = trim((string) $this->command->ask('Enum values'));
            $parts = preg_split('/[\s,]+/', $rawValue);

            if (! is_array($parts) || $parts === []) {
                $this->command->error('Invalid input.');
                continue;
            }

            $values = [];

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $values[] = $part;
                }
            }

            if ($values !== []) {
                return array_values(array_unique($values));
            }

            $this->command->error('Invalid input.');
        } while (true);
    }

    protected function formatDefaultValue(string $value, string $columnType): string
    {
        if ($columnType === 'boolean') {
            if (in_array(strtolower($value), ['1', 'true', 'yes'], true)) {
                return 'true';
            }

            if (in_array(strtolower($value), ['0', 'false', 'no'], true)) {
                return 'false';
            }
        }

        if (in_array($columnType, ['integer', 'bigInteger', 'decimal', 'float'], true) && is_numeric($value)) {
            return $value;
        }

        return var_export($value, true);
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function persistColumnsInMigrationFile(string $migrationName, array $columns): void
    {
        if ($columns === []) {
            return;
        }

        $migrationPath = $this->findMigrationPath($migrationName);

        if ($migrationPath === null) {
            $this->command->warn('Migration file was created but columns could not be auto-inserted.');

            return;
        }

        $content = file_get_contents($migrationPath);

        if (! is_string($content)) {
            $this->command->warn('Migration file was created but columns could not be auto-inserted.');

            return;
        }

        $replacements = 0;
        $updatedContent = $this->injectColumnsIntoMigrationContent($content, $columns, $replacements);

        if (! is_string($updatedContent) || $replacements === 0) {
            $this->command->warn('Migration file was created but columns could not be auto-inserted.');

            return;
        }

        if (file_put_contents($migrationPath, $updatedContent) === false) {
            $this->command->warn('Migration file was created but columns could not be auto-inserted.');
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function injectColumnsIntoMigrationContent(string $content, array $columns, int &$replacements): ?string
    {
        $replacements = 0;

        $updated = preg_replace_callback(
            '/^(\s*)\$table->id\(\);\s*$/m',
            function (array $matches) use ($columns): string {
                $indent = (string) ($matches[1] ?? '            ');
                $columnBlock = implode(PHP_EOL, array_map(static fn (string $column): string => $indent . $column, $columns));

                return $matches[0] . PHP_EOL . $columnBlock;
            },
            $content,
            1,
            $replacements,
        );

        if (is_string($updated) && $replacements > 0) {
            return $updated;
        }

        $columnBlock = implode(PHP_EOL, array_map(static fn (string $column): string => '            ' . $column, $columns)) . PHP_EOL;

        return preg_replace(
            '/(function\s*\(Blueprint \$table\)\s*\{\R)/',
            '$1' . $columnBlock,
            $content,
            1,
            $replacements,
        );
    }

    protected function findMigrationPath(string $migrationName): ?string
    {
        $migrationDirectory = base_path('database/migrations');

        if (! is_dir($migrationDirectory)) {
            return null;
        }

        $directMatch = glob($migrationDirectory . DIRECTORY_SEPARATOR . '*_' . $migrationName . '.php');

        if (is_array($directMatch) && $directMatch !== []) {
            usort($directMatch, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

            return $directMatch[0];
        }

        $fallbackMatches = glob($migrationDirectory . DIRECTORY_SEPARATOR . '*.php');

        if (! is_array($fallbackMatches) || $fallbackMatches === []) {
            return null;
        }

        $filtered = array_values(array_filter($fallbackMatches, static fn (string $path): bool => str_contains(basename($path), $migrationName)));

        if ($filtered === []) {
            return null;
        }

        usort($filtered, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $filtered[0];
    }

    protected function askClassName(string $question): string
    {
        do {
            $rawValue = trim((string) $this->command->ask($question));

            if (! $this->validator->validateNotEmpty($rawValue)) {
                $this->command->error('Invalid input.');
                continue;
            }

            $value = $this->validator->normalizeClassName($rawValue);

            if (! $this->validator->validateClassName($value)) {
                $this->command->error('Invalid class name format');
                $this->command->warn('Please follow PHP naming conventions.');
                continue;
            }

            if ($value !== $rawValue) {
                $this->command->info(sprintf('Using class name: %s', $value));
            }

            return $value;
        } while (true);
    }

    protected function askTableName(string $question): string
    {
        do {
            $value = trim((string) $this->command->ask($question));

            if (! $this->validator->validateNotEmpty($value)) {
                $this->command->error('Invalid input.');
                continue;
            }

            if (! $this->validator->validateTableName($value)) {
                $this->command->error('Invalid table name format');
                continue;
            }

            return $value;
        } while (true);
    }

    protected function ensureSeederSuffix(string $name): string
    {
        $baseName = preg_replace('/Seeder$/i', '', $name);

        if (! is_string($baseName) || $baseName === '') {
            return 'Seeder';
        }

        return $baseName . 'Seeder';
    }

    protected function logAction(string $message): void
    {
        try {
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/lokyhelpme.log'),
            ])->info($message);
        } catch (Throwable) {
            // Logging is optional; ignore failures.
        }
    }
}
