<?php

namespace LokyHelpMe\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class ControllerGenerator
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
        $name = $this->ensureControllerSuffix($this->askClassName('Controller name?'));
        $type = $this->command->choice('Controller type', ['Normal', 'Resource', 'API Resource'], 'Normal');

        $arguments = ['name' => $name];

        if ($type === 'Resource') {
            $arguments['--resource'] = true;
        } elseif ($type === 'API Resource') {
            $arguments['--api'] = true;
        }

        $exitCode = $this->preview->previewAndRun('make:controller', $arguments);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Controller created successfully.');
            $this->logAction(sprintf('User generated controller: %s', $name));
        } else {
            $this->command->error('Controller generation failed.');
        }

        return $exitCode;
    }

    public function api(): int
    {
        $name = $this->ensureControllerSuffix($this->askClassName('Controller name?'));

        $exitCode = $this->preview->previewAndRun('make:controller', [
            'name' => $name,
            '--api' => true,
        ]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('API controller created successfully.');
            $this->logAction(sprintf('User generated API controller: %s', $name));
        } else {
            $this->command->error('API controller generation failed.');
        }

        return $exitCode;
    }

    public function generateMiddleware(): int
    {
        $name = $this->askClassName('Middleware name?');

        $exitCode = $this->preview->previewAndRun('make:middleware', ['name' => $name]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Middleware created successfully.');
            $this->logAction(sprintf('User generated middleware: %s', $name));
        } else {
            $this->command->error('Middleware generation failed.');
        }

        return $exitCode;
    }

    public function generateRequestValidation(): int
    {
        $name = $this->ensureRequestSuffix($this->askClassName('Request class name?'));

        $exitCode = $this->preview->previewAndRun('make:request', ['name' => $name]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Request validation created successfully.');
            $this->logAction(sprintf('User generated request validation: %s', $name));
        } else {
            $this->command->error('Request validation generation failed.');
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

    protected function ensureControllerSuffix(string $name): string
    {
        $baseName = preg_replace('/Controller$/i', '', $name);

        if (! is_string($baseName) || $baseName === '') {
            return 'Controller';
        }

        return $baseName . 'Controller';
    }

    protected function ensureRequestSuffix(string $name): string
    {
        $baseName = preg_replace('/Request$/i', '', $name);

        if (! is_string($baseName) || $baseName === '') {
            return 'Request';
        }

        return $baseName . 'Request';
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
