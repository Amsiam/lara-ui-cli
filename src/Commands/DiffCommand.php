<?php

namespace Amsiam\LaraUiCli\Commands;

use Amsiam\LaraUiCli\ComponentRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DiffCommand extends Command
{
    protected $signature = 'ui:diff
                            {component? : Component to check for updates}
                            {--all : Check all installed components}';

    protected $description = 'Check for differences between local and remote components';

    public function __construct(
        protected ComponentRegistry $registry,
        protected Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->registry->isInitialized()) {
            $this->components->error('LaraUI is not initialized. Run: php artisan ui:init');
            return self::FAILURE;
        }

        $componentsToCheck = $this->getComponentsToCheck();

        if (empty($componentsToCheck)) {
            $this->components->warn('No installed components to check.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Checking ' . count($componentsToCheck) . ' component(s) for updates...');
        $this->newLine();

        $hasUpdates = false;

        foreach ($componentsToCheck as $name) {
            $result = $this->checkComponent($name);
            if ($result) {
                $hasUpdates = true;
            }
        }

        $this->newLine();

        if ($hasUpdates) {
            $this->components->warn('Some components have updates available.');
            $this->line('  Run <fg=yellow>php artisan ui:add [component] --force</> to update.');
        } else {
            $this->components->info('All components are up to date!');
        }

        return self::SUCCESS;
    }

    protected function getComponentsToCheck(): array
    {
        $component = $this->argument('component');

        if ($component) {
            $installed = $this->registry->getInstalledComponents();
            return in_array($component, $installed) ? [$component] : [];
        }

        if ($this->option('all')) {
            return $this->registry->getInstalledComponents();
        }

        // Default: check all installed
        return $this->registry->getInstalledComponents();
    }

    protected function checkComponent(string $name): bool
    {
        $component = $this->registry->getComponent($name);

        if (!$component) {
            return false;
        }

        $componentsPath = base_path($this->registry->getConfig('aliases.utils', 'app/View/Components/LaraUi'));
        $viewsPath = base_path($this->registry->getConfig('aliases.components', 'resources/views/components/ui'));

        $phpDiffs = 0;
        $bladeDiffs = 0;

        // Check PHP files
        foreach ($component['files']['php'] ?? [] as $file) {
            $localPath = "{$componentsPath}/{$file}";

            if ($this->files->exists($localPath)) {
                try {
                    $remote = $this->registry->fetchComponentFile("src/Components/{$file}");
                    $local = $this->files->get($localPath);

                    // Normalize for comparison (ignore namespace changes)
                    $remoteNorm = $this->normalizeForComparison($remote);
                    $localNorm = $this->normalizeForComparison($local);

                    if ($remoteNorm !== $localNorm) {
                        $phpDiffs++;
                    }
                } catch (\Exception $e) {
                    // Skip if can't fetch
                }
            }
        }

        // Check Blade files
        foreach ($component['files']['blade'] ?? [] as $file) {
            $localPath = "{$viewsPath}/{$file}";

            if ($this->files->exists($localPath)) {
                try {
                    $remote = $this->registry->fetchComponentFile("resources/views/components/{$file}");
                    $local = $this->files->get($localPath);

                    // Normalize for comparison (ignore prefix changes)
                    $remoteNorm = $this->normalizeBladeForComparison($remote);
                    $localNorm = $this->normalizeBladeForComparison($local);

                    if ($remoteNorm !== $localNorm) {
                        $bladeDiffs++;
                    }
                } catch (\Exception $e) {
                    // Skip if can't fetch
                }
            }
        }

        $totalDiffs = $phpDiffs + $bladeDiffs;

        if ($totalDiffs > 0) {
            $this->components->twoColumnDetail(
                "<fg=blue>{$component['name']}</>",
                "<fg=yellow>⚠ {$totalDiffs} file(s) differ</>"
            );
            return true;
        }

        $this->components->twoColumnDetail(
            "<fg=blue>{$component['name']}</>",
            '<fg=green>✓ Up to date</>'
        );

        return false;
    }

    protected function normalizeForComparison(string $content): string
    {
        // Remove namespace line
        $content = preg_replace('/namespace\s+[^;]+;/', '', $content);

        // Remove whitespace variations
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }

    protected function normalizeBladeForComparison(string $content): string
    {
        // Normalize component prefix
        $content = preg_replace('/x-[a-z]+::/', 'x-PREFIX::', $content);

        // Remove whitespace variations
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }
}
