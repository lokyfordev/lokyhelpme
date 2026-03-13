<?php

namespace LokyHelpMe\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class PivotModelGenerator
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
        $pivotName = $this->askClassName('Pivot model name?');

        $exitCode = $this->preview->previewAndRun('make:model', [
            'name' => $pivotName,
            '--pivot' => true,
        ]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Pivot model created successfully.');
            $this->logAction(sprintf('User created pivot model: %s', $pivotName));
        } else {
            $this->command->error('Pivot model generation failed.');
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
