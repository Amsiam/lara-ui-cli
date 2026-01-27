# LaraUI CLI

A CLI tool for adding LaraUI components to your Laravel project. Similar to shadcn/ui CLI, this tool fetches components from the LaraUI registry and installs them directly into your project.

## Installation

```bash
composer require amsiam/lara-ui-cli --dev
```

## Quick Start

### 1. Initialize your project

```bash
php artisan ui:init
```

This will:
- Create a `lara-ui.json` configuration file
- Install CSS variables to `resources/css/lara-ui.css`
- Update your `app.css` to import the styles

### 2. Add components

```bash
# Add specific components
php artisan ui:add button card input

# Add all components
php artisan ui:add all

# Interactive selection
php artisan ui:add
```

### 3. Use components

```blade
<x-ui::button variant="primary">Click me</x-ui::button>

<x-ui::card>
    <x-ui::card-header>
        <x-ui::card-title>Hello</x-ui::card-title>
    </x-ui::card-header>
    <x-ui::card-content>
        Content here
    </x-ui::card-content>
</x-ui::card>
```

## Commands

### `ui:init`

Initialize LaraUI in your project.

```bash
php artisan ui:init [--force] [--yes]
```

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing configuration |
| `--yes` | Skip prompts, use defaults |

### `ui:add`

Add components to your project.

```bash
php artisan ui:add [components...] [--all] [--force] [--deps] [--path=]
```

| Option | Description |
|--------|-------------|
| `--all` | Install all available components |
| `--force` | Overwrite existing files |
| `--deps` | Skip dependency installation |
| `--path=` | Custom installation path |

**Examples:**

```bash
# Add specific components
php artisan ui:add button card dialog

# Add all components
php artisan ui:add all

# Interactive multi-select
php artisan ui:add

# Force overwrite existing
php artisan ui:add button --force

# Custom path
php artisan ui:add button --path=resources/ui
```

### `ui:list`

List available components.

```bash
php artisan ui:list [--category=] [--installed] [--available] [--json] [--refresh]
```

| Option | Description |
|--------|-------------|
| `--category=` | Filter by category (core, forms, feedback, etc.) |
| `--installed` | Show only installed components |
| `--available` | Show only not-installed components |
| `--json` | Output as JSON |
| `--refresh` | Refresh the registry cache |

### `ui:diff`

Check for updates to installed components.

```bash
php artisan ui:diff [component] [--all]
```

| Option | Description |
|--------|-------------|
| `component` | Specific component to check |
| `--all` | Check all installed components |

## Configuration

After running `ui:init`, a `lara-ui.json` file is created in your project root:

```json
{
    "registry": "https://raw.githubusercontent.com/amsiam/lara-ui/main",
    "aliases": {
        "components": "resources/views/components/ui",
        "utils": "app/View/Components/LaraUi"
    },
    "tailwind": {
        "css": "resources/css/app.css"
    },
    "prefix": "ui"
}
```

### Configuration Options

| Key | Description | Default |
|-----|-------------|---------|
| `registry` | URL of the component registry | GitHub raw URL |
| `aliases.components` | Where Blade views are installed | `resources/views/components/ui` |
| `aliases.utils` | Where PHP classes are installed | `app/View/Components/LaraUi` |
| `tailwind.css` | Path to your main CSS file | `resources/css/app.css` |
| `prefix` | Component prefix (e.g., `x-ui::`) | `ui` |

### Custom Registry

You can host your own component registry by setting the `registry` URL:

```json
{
    "registry": "https://your-domain.com/lara-ui"
}
```

The registry must have a `registry.json` file and the component files at the expected paths.

## How It Works

1. **Initialization** (`ui:init`)
   - Creates configuration file
   - Downloads CSS variables from registry
   - Sets up your project for LaraUI

2. **Adding Components** (`ui:add`)
   - Fetches component files from the registry (GitHub)
   - Resolves and installs dependencies automatically
   - Transforms namespaces and prefixes for your project
   - Copies files to your configured paths

3. **Component Ownership**
   - Components are copied to your project
   - You own and can customize them fully
   - No runtime dependency on the registry

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Tailwind CSS 4
- Alpine.js 3.x (for interactive components)

## Dependencies

After installing components, ensure you have these packages:

```bash
# Required for class merging
composer require gehrisandro/tailwind-merge-laravel
```

## License

MIT License
