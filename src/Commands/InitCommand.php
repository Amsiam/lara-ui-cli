<?php

namespace Amsiam\LaraUiCli\Commands;

use Amsiam\LaraUiCli\ComponentRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InitCommand extends Command
{
    protected $signature = 'ui:init
                            {--force : Overwrite existing configuration}
                            {--yes : Skip confirmation prompts}';

    protected $description = 'Initialize LaraUI in your Laravel project';

    public function __construct(
        protected ComponentRegistry $registry,
        protected Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Initializing LaraUI...');
        $this->newLine();

        // Check if already initialized
        if ($this->registry->isInitialized() && !$this->option('force')) {
            $this->components->warn('LaraUI is already initialized.');

            if (!$this->option('yes') && !confirm('Do you want to reinitialize?', false)) {
                return self::SUCCESS;
            }
        }

        // Gather configuration
        $config = $this->gatherConfig();

        // Save configuration
        $this->registry->saveConfig($config);
        $this->components->task('Creating lara-ui.json', fn() => true);

        // Install CSS
        $this->installStyles();

        // Install TailwindMerge if not present
        $this->checkDependencies();

        $this->newLine();
        $this->components->info('LaraUI initialized successfully!');

        $this->newLine();
        $this->line('  <fg=gray>Next steps:</>');
        $this->line('  <fg=gray>1.</> Add components: <fg=yellow>php artisan ui:add button card input</>');
        $this->line('  <fg=gray>2.</> Or browse:       <fg=yellow>php artisan ui:list</>');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function gatherConfig(): array
    {
        if ($this->option('yes')) {
            return $this->getDefaultConfig();
        }

        $this->line('  <fg=blue>Configuration</>');
        $this->newLine();

        // Components path
        $componentsPath = text(
            label: 'Where would you like to install component views?',
            default: 'resources/views/components/ui',
            hint: 'Blade view files will be placed here'
        );

        // PHP classes path
        $classesPath = text(
            label: 'Where would you like to install PHP component classes?',
            default: 'app/View/Components/LaraUi',
            hint: 'PHP class files will be placed here'
        );

        // CSS path
        $cssPath = text(
            label: 'Where is your main CSS file?',
            default: 'resources/css/app.css',
            hint: 'LaraUI styles will be imported here'
        );

        // Component prefix
        $prefix = text(
            label: 'Component prefix (e.g., x-ui::button)',
            default: 'ui',
            hint: 'Used when referencing components in Blade'
        );

        return [
            'aliases' => [
                'components' => $componentsPath,
                'utils' => $classesPath,
            ],
            'tailwind' => [
                'css' => $cssPath,
            ],
            'prefix' => $prefix,
            'registry' => ComponentRegistry::DEFAULT_REGISTRY,
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'aliases' => [
                'components' => 'resources/views/components/ui',
                'utils' => 'app/View/Components/LaraUi',
            ],
            'tailwind' => [
                'css' => 'resources/css/app.css',
            ],
            'prefix' => 'ui',
            'registry' => ComponentRegistry::DEFAULT_REGISTRY,
        ];
    }

    protected function installStyles(): void
    {
        $this->components->task('Installing CSS variables', function () {
            try {
                $styles = $this->registry->fetchStyles();
                $destPath = resource_path('css/lara-ui.css');

                $this->files->ensureDirectoryExists(dirname($destPath));
                $this->files->put($destPath, $styles);

                // Update app.css
                $this->updateAppCss();

                $this->updateComponentNameSpace();

                return true;
            } catch (\Exception $e) {
                $this->components->error($e->getMessage());
                return false;
            }
        });
    }

    protected function updateComponentNameSpace(): void
    {
        $prefix = $this->registry->getConfig('prefix', 'ui');
        $appServiceProviderPath = app_path('Providers/AppServiceProvider.php');

        if (!$this->files->exists($appServiceProviderPath)) {
            return;
        }

        $content = $this->files->get($appServiceProviderPath);
        $namespaceLine = "Blade::componentNamespace('App\\\\View\\\\Components\\\\LaraUi', '{$prefix}');";

        if (str_contains($content, 'Blade::componentNamespace') && !str_contains($content, $namespaceLine)) {
            // Replace existing namespace line
            $content = preg_replace(
                "/Blade::componentNamespace\('App\\\\\\\\View\\\\\\\\Components\\\\\\\\LaraUi', '.*?'\);/",
                $namespaceLine,
                $content
            );
        } elseif (!str_contains($content, 'Blade::componentNamespace')) {
            // Add new namespace line in boot method
            $content = preg_replace(
                "/public function boot\(\): void\n\s*{/",
                "public function boot(): void\n    {\n        {$namespaceLine}",
                $content
            );
        }

        $this->files->put($appServiceProviderPath, $content);
    }

    protected function updateAppCss(): void
    {
        $cssPath = $this->registry->getConfig('tailwind.css', 'resources/css/app.css');
        $appCssPath = base_path($cssPath);

        if (!$this->files->exists($appCssPath)) {
            return;
        }

        $content = $this->files->get($appCssPath);
        $import = '@import "./lara-ui.css";';

        if (str_contains($content, 'lara-ui.css')) {
            return; // Already imported
        }

        // Add import after tailwindcss import or at beginning
        if (str_contains($content, '@import "tailwindcss"')) {
            $content = str_replace(
                '@import "tailwindcss";',
                "@import \"tailwindcss\";\n{$import}",
                $content
            );
        } else {
            $content = "{$import}\n\n{$content}";
        }

        $this->files->put($appCssPath, $content);
    }

    protected function checkDependencies(): void
    {
        $composerPath = base_path('composer.json');

        if (!$this->files->exists($composerPath)) {
            return;
        }

        $composer = json_decode($this->files->get($composerPath), true);
        $require = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

        // Check for TailwindMerge
        if (!isset($require['gehrisandro/tailwind-merge-laravel'])) {
            $this->newLine();
            $this->components->warn('TailwindMerge is not installed.');
            $this->line('  Run: <fg=yellow>composer require gehrisandro/tailwind-merge-laravel</>');
        }
    }
}
