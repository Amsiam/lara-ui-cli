<?php

namespace Amsiam\LaraUiCli\Commands;

use Amsiam\LaraUiCli\ComponentRegistry;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'ui:list
                            {--category= : Filter by category}
                            {--installed : Show only installed components}
                            {--available : Show only available (not installed) components}
                            {--json : Output as JSON}
                            {--refresh : Refresh the component registry cache}';

    protected $description = 'List available LaraUI components';

    public function __construct(
        protected ComponentRegistry $registry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $components = $this->registry->fetchManifest($this->option('refresh'));
        } catch (\Exception $e) {
            $this->components->error("Failed to fetch registry: {$e->getMessage()}");
            return self::FAILURE;
        }

        $filtered = $this->filterComponents();

        if ($this->option('json')) {
            $this->line(json_encode($filtered, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->displayComponents($filtered);

        return self::SUCCESS;
    }

    protected function filterComponents(): array
    {
        $components = $this->registry->getComponents();
        $categoryFilter = $this->option('category');
        $installedOnly = $this->option('installed');
        $availableOnly = $this->option('available');
        $installed = $this->registry->getInstalledComponents();

        if ($categoryFilter) {
            $components = array_filter($components, fn($c) => $c['category'] === $categoryFilter);
        }

        if ($installedOnly) {
            $components = array_filter($components, fn($c, $k) => in_array($k, $installed), ARRAY_FILTER_USE_BOTH);
        }

        if ($availableOnly) {
            $components = array_filter($components, fn($c, $k) => !in_array($k, $installed), ARRAY_FILTER_USE_BOTH);
        }

        return $components;
    }

    protected function displayComponents(array $components): void
    {
        $categories = $this->registry->getCategories();
        $installed = $this->registry->getInstalledComponents();

        $this->newLine();

        // Show registry info
        $registryUrl = $this->registry->getRegistryUrl();
        $this->line("  <fg=gray>Registry:</> {$registryUrl}");
        $this->newLine();

        $this->components->info('LaraUI Components (' . count($components) . ' total)');
        $this->newLine();

        // Group by category
        $grouped = [];
        foreach ($components as $key => $component) {
            $category = $component['category'];
            $grouped[$category][$key] = $component;
        }

        // Sort categories
        ksort($grouped);

        foreach ($grouped as $category => $categoryComponents) {
            $categoryName = $categories[$category]['name'] ?? ucfirst($category);
            $categoryDesc = $categories[$category]['description'] ?? '';

            $this->line("  <fg=yellow;options=bold>{$categoryName}</> <fg=gray>- {$categoryDesc}</>");
            $this->newLine();

            foreach ($categoryComponents as $key => $component) {
                $isInstalled = in_array($key, $installed);
                $status = $isInstalled ? '<fg=green>✓</>' : '<fg=gray>○</>';
                $name = str_pad($component['name'], 18);

                $deps = '';
                if (!empty($component['dependencies'])) {
                    $deps = '<fg=gray>[requires: ' . implode(', ', $component['dependencies']) . ']</>';
                }

                $fileCount = count($component['files']['php'] ?? []) + count($component['files']['blade'] ?? []);

                $this->line("    {$status} <fg=blue>{$name}</> <fg=gray>({$fileCount} files)</> {$component['description']} {$deps}");
            }

            $this->newLine();
        }

        // Summary
        $totalInstalled = count($installed);
        $totalAvailable = count($this->registry->getComponents());

        $this->line("  <fg=gray>Installed:</> {$totalInstalled}/{$totalAvailable}");
        $this->newLine();

        // Help
        $this->line('  <fg=gray>Commands:</>');
        $this->line('    <fg=yellow>php artisan ui:add button card</>  <fg=gray>Add specific components</>');
        $this->line('    <fg=yellow>php artisan ui:add all</>          <fg=gray>Add all components</>');
        $this->line('    <fg=yellow>php artisan ui:add</>              <fg=gray>Interactive selection</>');
        $this->newLine();
    }
}
