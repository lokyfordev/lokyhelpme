<?php

namespace LokyHelpMe\Console;

use Illuminate\Console\Command;
use LokyHelpMe\Services\CommandGenerator;
use LokyHelpMe\Services\ControllerGenerator;
use LokyHelpMe\Services\EventGenerator;
use LokyHelpMe\Services\JobGenerator;
use LokyHelpMe\Services\ListenerGenerator;
use LokyHelpMe\Services\MigrationGenerator;
use LokyHelpMe\Services\ModelGenerator;
use LokyHelpMe\Services\PivotModelGenerator;
use LokyHelpMe\Services\ResourceGenerator;

class LokyHelpMeCommand extends Command
{
    protected $signature = 'lokyhelpme';

    protected $description = 'Interactive Laravel command helper for beginners';

    public function handle(): int
    {
        $this->banner();

        $exitCode = self::SUCCESS;

        do {
            $selection = $this->menu();
            $exitCode = $this->route($selection);
        } while ($this->confirm('Do you want to create something else?', false));

        $this->info('LokyHelpMe session finished.');

        return $exitCode;
    }

    /**
     * @return array<string, string>
     */
    protected function menuOptions(): array
    {
        return [
            '1' => 'Model',
            '2' => 'Controller',
            '3' => 'Migration',
            '4' => 'Seeder',
            '5' => 'Factory',
            '6' => 'Policy',
            '7' => 'Middleware',
            '8' => 'Request Validation',
            '9' => 'Event',
            '10' => 'Listener',
            '11' => 'Job',
            '12' => 'Command',
            '13' => 'Resource',
            '14' => 'Pivot Model',
            '15' => 'API Controller',
        ];
    }

    protected function banner(): void
    {
        $this->line('----------------------------------');
        $this->info('LokyHelpMe Laravel Assistant');
        $this->line('Interactive Laravel command generator');
        $this->line('----------------------------------');
    }

    protected function menu(): string
    {
        $this->newLine();
        $this->line('What do you want to create?');

        // foreach ($this->menuOptions() as $key => $label) {
        //     $this->line(sprintf('%s %s', $key, $label));
        // }

        return $this->choice('Choose an option', array_values($this->menuOptions()), 'Model');
    }

    protected function route(string $selection): int
    {
        return match ($selection) {
            'Model' => $this->makeService(ModelGenerator::class)->run(),
            'Controller' => $this->makeService(ControllerGenerator::class)->run(),
            'Migration' => $this->makeService(MigrationGenerator::class)->run(),
            'Seeder' => $this->makeService(MigrationGenerator::class)->generateSeeder(),
            'Factory' => $this->makeService(ModelGenerator::class)->generateFactory(),
            'Policy' => $this->makeService(ModelGenerator::class)->generatePolicy(),
            'Middleware' => $this->makeService(ControllerGenerator::class)->generateMiddleware(),
            'Request Validation' => $this->makeService(ControllerGenerator::class)->generateRequestValidation(),
            'Event' => $this->makeService(EventGenerator::class)->run(),
            'Listener' => $this->makeService(ListenerGenerator::class)->run(),
            'Job' => $this->makeService(JobGenerator::class)->run(),
            'Command' => $this->makeService(CommandGenerator::class)->run(),
            'Resource' => $this->makeService(ResourceGenerator::class)->run(),
            'Pivot Model' => $this->makeService(PivotModelGenerator::class)->run(),
            'API Controller' => $this->makeService(ControllerGenerator::class)->api(),
            default => self::FAILURE,
        };
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $serviceClass
     * @return T
     */
    protected function makeService(string $serviceClass): object
    {
        if ($this->laravel->bound($serviceClass)) {
            /** @var T $boundService */
            $boundService = $this->laravel->make($serviceClass);

            return $boundService;
        }

        /** @var T $service */
        $service = $this->laravel->makeWith($serviceClass, ['command' => $this]);

        return $service;
    }
}
