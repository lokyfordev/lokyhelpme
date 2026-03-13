<?php

namespace LokyHelpMe\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class ResourceGenerator
{
    protected InputValidator $validator;

    protected CommandPreview $preview;

    public function __construct(protected Command $command)
    {
        $this->validator = new InputValidator();
        $this->preview = new CommandPreview($command);
    }

    public function run(): int
    {
        $resourceName = $this->ensureResourceSuffix($this->askClassName('Resource name?'));
        $exitCode = $this->preview->previewAndRun('make:resource', ['name' => $resourceName]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Resource created successfully.');
            $this->logAction(sprintf('User created resource: %s', $resourceName));
        } else {
            $this->command->error('Resource generation failed.');
        }

        return $exitCode;
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
                $this->command->error('Invalid class name.');
                $this->command->warn('Please follow PHP naming conventions.');
                continue;
            }

            if ($value !== $rawValue) {
                $this->command->info(sprintf('Using class name: %s', $value));
            }

            return $value;
        } while (true);
    }

    protected function ensureResourceSuffix(string $name): string
    {
        $baseName = preg_replace('/Resource$/i', '', $name);

        if (! is_string($baseName) || $baseName === '') {
            return 'Resource';
        }

        return $baseName . 'Resource';
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
