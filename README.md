# LokyHelpMe

Interactive Artisan assistant for Laravel beginners.

`lokyhelpme` helps users generate Laravel classes and migrations through guided CLI questions, so they do not need to remember Artisan syntax.

## Install

```bash
composer require lokyfordev/lokyhelpme
```

Run:

```bash
php artisan lokyhelpme
```

## Main Command

```bash
php artisan lokyhelpme
```

Startup output:

```text
----------------------------------
LokyHelpMe Laravel Assistant
Interactive Laravel command generator
----------------------------------
```

## Menu

1. Model
2. Controller
3. Migration
4. Seeder
5. Factory
6. Policy
7. Middleware
8. Request Validation
9. Event
10. Listener
11. Job
12. Command
13. Resource
14. Pivot Model
15. API Controller

## What Gets Generated

| Option | Artisan command |
|---|---|
| Model | `make:model` (`-m`, `-f`, `-s`, `-c` optional) |
| Controller | `make:controller` (`Normal`, `--resource`, `--api`) |
| Migration | `make:migration` or `make:model -m` (guided) |
| Seeder | `make:seeder` |
| Factory | `make:factory --model=Model` |
| Policy | `make:policy --model=Model` |
| Middleware | `make:middleware` |
| Request Validation | `make:request` |
| Event | `make:event` |
| Listener | `make:listener` (optional `--event`) |
| Job | `make:job` |
| Command | `make:command` |
| Resource | `make:resource` |
| Pivot Model | `make:model --pivot` |
| API Controller | `make:controller --api` |

## Migration Assistant

When selecting `Migration`, users get:

1. Create new table
2. Create model with migration
3. Modify existing table

### Column Builder

For all migration flows, LokyHelpMe can ask for columns interactively.

- Type `end` to finish adding columns
- Supported types:
  - `string`, `text`, `integer`, `bigInteger`, `boolean`
  - `decimal`, `float`, `date`, `dateTime`, `timestamp`
  - `enum`, `json`, `uuid`
- Supports options:
  - nullable
  - unique
  - default value
  - string length
  - decimal/float precision + scale

Enum values are entered once using commas or spaces:

```text
admin superadmin helper
```

Generated:

```php
$table->enum('role', ['admin', 'superadmin', 'helper']);
```

## Validation And UX

- Empty input is rejected
- Class names are validated against PHP naming conventions
- Table/column names are validated for migration-safe format
- Lowercase/dirty class input is normalized:
  - `seler` -> `Seler`
  - `user_profile` -> `UserProfile`
- Every command is previewed before execution
- User must confirm execution

## Database Table Detection

For `Modify existing table`, LokyHelpMe fetches tables from the active Laravel DB connection and lists them for selection.

If DB connection fails:

```text
Database connection failed.
Check your .env configuration.
```

If no tables are found:

```text
No tables found in the current database.
```

## Logging

LokyHelpMe logs generation actions to:

```text
storage/logs/lokyhelpme.log
```

Examples:

- `User generated model: Post`
- `User modified table: users`
- `User created event: UserRegistered`

## Package Structure

```text
lokyhelpme/
├── src/
│   ├── Console/
│   │   └── LokyHelpMeCommand.php
│   ├── Services/
│   │   ├── ModelGenerator.php
│   │   ├── ControllerGenerator.php
│   │   ├── MigrationGenerator.php
│   │   ├── EventGenerator.php
│   │   ├── ListenerGenerator.php
│   │   ├── JobGenerator.php
│   │   ├── CommandGenerator.php
│   │   ├── ResourceGenerator.php
│   │   ├── PivotModelGenerator.php
│   │   ├── TableInspector.php
│   │   ├── InputValidator.php
│   │   └── CommandPreview.php
│   └── LokyHelpMeServiceProvider.php
└── composer.json
```

## Compatibility

- PHP `^8.1`
- Laravel `10`, `11`, `12` (via Illuminate components)

## Auto-discovery

`composer.json` includes:

```json
"extra": {
  "laravel": {
    "providers": [
      "LokyHelpMe\\LokyHelpMeServiceProvider"
    ]
  }
}
```

## Development

Run tests:

```bash
php artisan test
```

Run formatter:

```bash
vendor/bin/pint
```

## License

MIT
