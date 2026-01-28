<?php

namespace Amsiam\LaraUiCli;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

class ComponentRegistry
{
    protected Client $client;
    protected array $config;
    protected ?array $manifest = null;

    // Default registry URL - can be overridden in config
    public const DEFAULT_REGISTRY = "https://raw.githubusercontent.com/amsiam/lara-ui/main";
    public const MANIFEST_FILE = 'registry.json';
    public const CACHE_TTL = 3600; // 1 hour

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);

        $this->config = $this->loadConfig();
    }

    protected function loadConfig(): array
    {
        $configPath = $this->getConfigPath();

        if (file_exists($configPath)) {
            return json_decode(file_get_contents($configPath), true) ?? [];
        }

        return [
            'registry' => self::DEFAULT_REGISTRY,
            'typescript' => false,
            'tailwind' => [
                'config' => 'tailwind.config.js',
                'css' => 'resources/css/app.css',
            ],
            'aliases' => [
                'components' => 'resources/views/components/ui',
                'utils' => 'app/View/Components',
            ],
        ];
    }

    public function getConfigPath(): string
    {
        return base_path('lara-ui.json');
    }

    public function saveConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        file_put_contents(
            $this->getConfigPath(),
            json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    public function isInitialized(): bool
    {
        return file_exists($this->getConfigPath());
    }

    public function getRegistryUrl(): string
    {
        return rtrim($this->config['registry'] ?? self::DEFAULT_REGISTRY, '/');
    }

    public function fetchManifest(bool $fresh = false): array
    {
        if ($this->manifest !== null && !$fresh) {
            return $this->manifest;
        }

        $cacheKey = 'lara-ui-manifest-' . md5($this->getRegistryUrl());

        if (!$fresh && Cache::has($cacheKey)) {
            $this->manifest = Cache::get($cacheKey);
            return $this->manifest;
        }

        try {
            $url = $this->getRegistryUrl() . '/' . self::MANIFEST_FILE;
            $response = $this->client->get($url);
            $this->manifest = json_decode($response->getBody()->getContents(), true);

            Cache::put($cacheKey, $this->manifest, self::CACHE_TTL);

            return $this->manifest;
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Failed to fetch component registry: {$e->getMessage()}"
            );
        }
    }

    public function getComponents(): array
    {
        $manifest = $this->fetchManifest();
        return $manifest['components'] ?? [];
    }

    public function getComponent(string $name): ?array
    {
        $components = $this->getComponents();
        return $components[$name] ?? null;
    }

    public function getCategories(): array
    {
        $manifest = $this->fetchManifest();
        return $manifest['categories'] ?? [];
    }

    public function fetchComponentFile(string $path): string
    {
        try {
            $url = $this->getRegistryUrl() . '/' . ltrim($path, '/');
            $response = $this->client->get($url);
            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Failed to fetch file '{$path}': {$e->getMessage()}"
            );
        }
    }

    public function fetchStyles(): string
    {
        return $this->fetchComponentFile('stubs/resources/css/lara-ui.css');
    }

    public function resolveDependencies(array $componentNames): array
    {
        $resolved = [];
        $toResolve = $componentNames;
        $components = $this->getComponents();

        while (!empty($toResolve)) {
            $name = array_shift($toResolve);

            if (in_array($name, $resolved)) {
                continue;
            }

            $component = $components[$name] ?? null;
            if (!$component) {
                continue;
            }

            // Add dependencies first
            foreach ($component['dependencies'] ?? [] as $dep) {
                if (!in_array($dep, $resolved) && !in_array($dep, $toResolve)) {
                    array_unshift($toResolve, $dep);
                }
            }

            $resolved[] = $name;
        }

        return $resolved;
    }

    public function getInstalledComponents(): array
    {
        $installed = [];
        $componentsPath = base_path($this->getConfig('aliases.utils', 'app/View/Components/LaraUi'));

        foreach ($this->getComponents() as $name => $component) {
            $phpFile = $component['files']['php'][0] ?? null;
            if ($phpFile && file_exists("{$componentsPath}/{$phpFile}")) {
                $installed[] = $name;
            }
        }

        return $installed;
    }
}
