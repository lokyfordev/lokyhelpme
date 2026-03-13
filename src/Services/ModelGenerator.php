<?php

namespace LokyHelpMe\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class ModelGenerator
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
        $name = $this->askClassName('Model name?');

        $arguments = ['name' => $name];

        if ($this->command->confirm('Generate migration?', false)) {
            $arguments['-m'] = true;
        }

        if ($this->command->confirm('Generate factory?', false)) {
            $arguments['-f'] = true;
        }

        if ($this->command->confirm('Generate seeder?', false)) {
            $arguments['-s'] = true;
        }

        if ($this->command->confirm('Generate controller?', false)) {
            $arguments['-c'] = true;
        }

        $exitCode = $this->preview->previewAndRun('make:model', $arguments);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Model created successfully.');
            $this->logAction(sprintf('User generated model: %s', $name));
        } else {
            $this->command->error('Model generation failed.');
        }

        return $exitCode;
    }

    public function generateFactory(): int
    {
        $model = $this->askClassName('Model name?');
        $factoryName = $model . 'Factory';

        $exitCode = $this->preview->previewAndRun('make:factory', [
            'name' => $factoryName,
            '--model' => $model,
        ]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Factory created successfully.');
            $this->logAction(sprintf('User generated factory: %s', $factoryName));
        } else {
            $this->command->error('Factory generation failed.');
        }

        return $exitCode;
    }

    public function generatePolicy(): int
    {
        $model = $this->askClassName('Model name?');
        $policyName = $model . 'Policy';

        $exitCode = $this->preview->previewAndRun('make:policy', [
            'name' => $policyName,
            '--model' => $model,
        ]);

        if ($exitCode === CommandPreview::CANCELLED) {
            return SymfonyCommand::SUCCESS;
        }

        if ($exitCode === SymfonyCommand::SUCCESS) {
            $this->command->info('Policy created successfully.');
            $this->logAction(sprintf('User generated policy: %s', $policyName));
        } else {
            $this->command->error('Policy generation failed.');
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
