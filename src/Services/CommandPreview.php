<?php

namespace LokyHelpMe\Services;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class CommandPreview
{
    public const CANCELLED = 99;

    public function __construct(protected Command $command)
    {
    }

    public function previewAndRun(string $artisanCommand, array $arguments): int
    {
        $this->command->line('');
        $this->command->line('Command to execute:');
        $this->command->line(sprintf('php artisan %s', $this->toCliString($artisanCommand, $arguments)));

        if (! $this->command->confirm('Execute this command?', true)) {
            $this->command->warn('Operation cancelled.');

            return self::CANCELLED;
        }

        try {
            return $this->command->call($artisanCommand, $arguments);
        } catch (Throwable $exception) {
            $this->command->error('Command execution failed.');
            $this->command->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }
    }

    protected function toCliString(string $artisanCommand, array $arguments): string
    {
        $parts = [$artisanCommand];

        foreach ($arguments as $key => $value) {
            if ($key === 'name') {
                $parts[] = $this->formatValue($value);

                continue;
            }

            if (is_bool($value)) {
                if ($value) {
                    $parts[] = (string) $key;
                }

                continue;
            }

            $parts[] = sprintf('%s=%s', $key, $this->formatValue($value));
        }

        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    protected function formatValue(mixed $value): string
    {
        $stringValue = (string) $value;

        if ($stringValue === '') {
            return '""';
        }

        if (preg_match('/\s/', $stringValue) === 1) {
            return '"' . str_replace('"', '\"', $stringValue) . '"';
        }

        return $stringValue;
    }
}
