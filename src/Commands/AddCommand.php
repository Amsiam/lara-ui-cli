<?php

namespace Amsiam\LaraUiCli\Commands;

use Amsiam\LaraUiCli\ComponentRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

class AddCommand extends Command
{
    protected $signature = 'ui:add
                            {components?* : Components to install}
                            {--all : Install all components}
                            {--force : Overwrite existing files}
                            {--deps : Skip installing dependencies}
                            {--path= : Custom installation path}';

    protected $description = 'Add LaraUI components to your project';

    protected string $componentsPath;
    protected string $viewsPath;

    public function __construct(
        protected ComponentRegistry $registry,
        protected Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Check if initialized
        if (!$this->registry->isInitialized()) {
            $this->components->error('LaraUI is not initialized. Run: php artisan ui:init');
            return self::FAILURE;
        }

        $this->setupPaths();

        // Get components to install
        $components = $this->getComponentsToInstall();

        if (empty($components)) {
            $this->components->warn('No components selected.');
            return self::SUCCESS;
        }

        // Resolve dependencies
        if (!$this->option('deps')) {
            $components = $this->registry->resolveDependencies($components);
        }

        $this->newLine();
        $this->components->info('Installing ' . count($components) . ' component(s)...');
        $this->newLine();

        $installed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($components as $componentName) {
            $result = $this->installComponent($componentName);

            match ($result) {
                'installed' => $installed++,
                'skipped' => $skipped++,
                'error' => $errors++,
            };
        }

        $this->newLine();
        $this->components->info("Done! Installed: {$installed}, Skipped: {$skipped}, Errors: {$errors}");

        // Show usage example
        if ($installed > 0) {
            $prefix = $this->registry->getConfig('prefix', 'ui');
            $firstComponent = $components[0] ?? 'button';

            $this->newLine();
            $this->line('  <fg=gray>Usage:</>');
            $this->line("  <fg=yellow><x-{$prefix}::{$firstComponent}>Content</x-{$prefix}::{$firstComponent}></>");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function setupPaths(): void
    {
        $customPath = $this->option('path');

        if ($customPath) {
            $this->componentsPath = base_path($customPath . '/Components');
            $this->viewsPath = base_path($customPath . '/views');
        } else {
            $this->componentsPath = base_path($this->registry->getConfig('aliases.utils', 'app/View/Components/LaraUi'));
            $this->viewsPath = base_path($this->registry->getConfig('aliases.components', 'resources/views/components/ui'));
        }
    }

    protected function getComponentsToInstall(): array
    {
        $input = $this->argument('components');

        // --all flag or "all" argument
        if ($this->option('all') || in_array('all', $input ?? [])) {
            return array_keys($this->registry->getComponents());
        }

        // Specific components provided
        if (!empty($input)) {
            return $this->validateComponents($input);
        }

        // Interactive selection
        return $this->interactiveSelection();
    }

    protected function validateComponents(array $components): array
    {
        $valid = [];
        $available = $this->registry->getComponents();

        foreach ($components as $component) {
            $normalized = Str::kebab($component);

            if (isset($available[$normalized])) {
                $valid[] = $normalized;
            } else {
                $this->components->warn("Component '{$component}' not found, skipping.");
            }
        }

        return $valid;
    }

    protected function interactiveSelection(): array
    {
        $components = $this->registry->getComponents();
        $categories = $this->registry->getCategories();
        $installed = $this->registry->getInstalledComponents();

        $options = [];
        foreach ($components as $key => $component) {
            $categoryName = $categories[$component['category']]['name'] ?? ucfirst($component['category']);
            $status = in_array($key, $installed) ? ' ✓' : '';
            $options[$key] = "[{$categoryName}] {$component['name']}{$status} - {$component['description']}";
        }

        return multiselect(
            label: 'Select components to install',
            options: $options,
            hint: 'Space to select, Enter to confirm. ✓ = already installed'
        );
    }

    protected function installComponent(string $name): string
    {
        $component = $this->registry->getComponent($name);

        if (!$component) {
            $this->components->twoColumnDetail(
                "<fg=red>{$name}</>",
                '<fg=red>✗ Not found</>'
            );
            return 'error';
        }

        $this->components->twoColumnDetail(
            "<fg=blue>{$component['name']}</>",
            '<fg=gray>Downloading...</>'
        );

        try {
            $installedFiles = 0;
            $skippedFiles = 0;

            // Install PHP files
            foreach ($component['files']['php'] ?? [] as $file) {
                $result = $this->installPhpFile($file);
                $result ? $installedFiles++ : $skippedFiles++;
            }

            // Install Blade files
            foreach ($component['files']['blade'] ?? [] as $file) {
                $result = $this->installBladeFile($file);
                $result ? $installedFiles++ : $skippedFiles++;
            }

            if ($installedFiles === 0 && $skippedFiles > 0) {
                $this->components->twoColumnDetail(
                    "<fg=blue>{$component['name']}</>",
                    '<fg=yellow>○ Skipped (already exists)</>'
                );
                return 'skipped';
            }

            $this->components->twoColumnDetail(
                "<fg=blue>{$component['name']}</>",
                '<fg=green>✓ Installed</>'
            );
            return 'installed';
        } catch (\Exception $e) {
            $this->components->twoColumnDetail(
                "<fg=red>{$component['name']}</>",
                "<fg=red>✗ {$e->getMessage()}</>"
            );
            return 'error';
        }
    }

    protected function installPhpFile(string $file): bool
    {
        $destPath = "{$this->componentsPath}/{$file}";

        if ($this->files->exists($destPath) && !$this->option('force')) {
            return false;
        }

        // Fetch from registry
        $content = $this->registry->fetchComponentFile("src/Components/{$file}");

        // Modify namespace based on installation path
        $content = $this->transformPhpContent($content);

        $this->files->ensureDirectoryExists(dirname($destPath));
        $this->files->put($destPath, $content);

        return true;
    }

    protected function installBladeFile(string $file): bool
    {
        $destPath = "{$this->viewsPath}/{$file}";

        if ($this->files->exists($destPath) && !$this->option('force')) {
            return false;
        }

        // Fetch from registry
        $content = $this->registry->fetchComponentFile("resources/views/components/{$file}");

        // Transform blade content
        $content = $this->transformBladeContent($content);

        $this->files->ensureDirectoryExists(dirname($destPath));
        $this->files->put($destPath, $content);

        return true;
    }

    protected function transformPhpContent(string $content): string
    {
        // Update namespace
        $namespace = $this->getPhpNamespace();
        $content = preg_replace(
            '/namespace\s+Amsiam\\\\LaraUi\\\\Components;/',
            "namespace {$namespace};",
            $content
        );

        // Update view references
        $prefix = $this->registry->getConfig('prefix', 'ui');
        $resourcePath = $this->registry->getConfig('aliases.components', 'resources/views/components/ui');

        $actualPath = str_replace('resources/views/', '', $resourcePath);

        //replace / with .
        $actualPath = str_replace('/', '.', $actualPath);

        $content = str_replace("'lu::components.", "'{$actualPath}.", $content);

        return $content;
    }

    protected function transformBladeContent(string $content): string
    {
        // Update component references
        $prefix = $this->registry->getConfig('prefix', 'ui');
        $content = str_replace('x-lu::', "x-{$prefix}::", $content);

        return $content;
    }

    protected function getPhpNamespace(): string
    {
        $path = $this->registry->getConfig('aliases.utils', 'app/View/Components/LaraUi');

        // Convert path to namespace (app/View/Components/LaraUi -> App\View\Components\LaraUi)
        $namespace = str_replace('/', '\\', $path);
        $namespace = Str::studly($namespace);

        // Ensure App namespace
        if (!str_starts_with($namespace, 'App\\')) {
            $namespace = 'App\\' . $namespace;
        }

        return $namespace;
    }
}
