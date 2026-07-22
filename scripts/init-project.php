<?php

/**
 * Template initializer.
 *
 * Rewrites the placeholder identity of this starter ("Laravel API Starter",
 * "laravel/laravel", APP_NAME=Laravel, ...) to your new project's name.
 *
 * Usage:
 *   php scripts/init-project.php
 *   php scripts/init-project.php --name="Acme API" --vendor=acme --slug=acme-api
 *   php scripts/init-project.php --name="Acme API" --no-interaction
 *   php scripts/init-project.php --dry-run
 *
 * Flags:
 *   --name="..."          Human-readable application name (APP_NAME, README title).
 *   --slug=...            Kebab-case project slug (composer package, docker volume).
 *   --vendor=...          Composer vendor (the part before "/").
 *   --description="..."   Composer package description.
 *   --dry-run             Show what would change without writing.
 *   --no-interaction      Don't prompt; use flags/defaults. Fails if --name missing.
 *   --self-destruct       Delete this script (and scripts/ if empty) when done.
 *   -h, --help            Show this help.
 */

const C_RESET = "\033[0m";
const C_BOLD = "\033[1m";
const C_GREEN = "\033[32m";
const C_YELLOW = "\033[33m";
const C_CYAN = "\033[36m";
const C_RED = "\033[31m";
const C_DIM = "\033[2m";

$root = dirname(__DIR__);
$args = parseArgs($GLOBALS['argv']);

if (isset($args['help']) || isset($args['h'])) {
    usage();
    exit(0);
}

$interactive = ! isset($args['no-interaction']);
$dryRun = isset($args['dry-run']);

line(C_BOLD . C_CYAN . "\n  Initialize project from template\n" . C_RESET);
if ($dryRun) {
    line(C_YELLOW . "  (dry run — no files will be written)\n" . C_RESET);
}

// --- Gather inputs -----------------------------------------------------------

$name = $args['name'] ?? null;
if ($name === null && $interactive) {
    $name = ask('Application name', 'My API');
}
if (! is_string($name) || trim($name) === '') {
    fail('An application name is required (pass --name="..." or run interactively).');
}
$name = trim($name);

$slug = $args['slug'] ?? slugify($name);
if ($interactive && ! isset($args['slug'])) {
    $slug = slugify(ask('Project slug (kebab-case)', $slug));
} else {
    $slug = slugify($slug);
}

$vendor = $args['vendor'] ?? defaultVendor();
if ($interactive && ! isset($args['vendor'])) {
    $vendor = slugify(ask('Composer vendor', $vendor));
} else {
    $vendor = slugify($vendor);
}

$description = $args['description'] ?? "$name — a Laravel JSON API.";
if ($interactive && ! isset($args['description'])) {
    $description = ask('Description', $description);
}

$package = "$vendor/$slug";

// --- Confirm -----------------------------------------------------------------

line("");
line(C_BOLD . "  Summary" . C_RESET);
summary('Application name', $name);
summary('Project slug', $slug);
summary('Composer package', $package);
summary('Description', $description);
line("");

if ($interactive && ! $dryRun && ! confirm('Apply these changes?', true)) {
    line(C_YELLOW . "  Aborted. Nothing was changed.\n" . C_RESET);
    exit(0);
}

// --- Define replacements -----------------------------------------------------

/**
 * Each entry: [relative path, [ [search, replace, isRegex], ... ] ].
 * A file that doesn't exist is skipped with a notice; a search string that
 * isn't found is reported but not fatal (it may already have been renamed).
 */
$plan = [
    'composer.json' => [
        ['"name": "laravel/laravel"', "\"name\": \"$package\"", false],
        [
            '"description": "The skeleton application for the Laravel framework."',
            '"description": "' . addcslashes($description, '"\\') . '"',
            false,
        ],
    ],
    '.env.example' => [
        ['/^APP_NAME=.*$/m', 'APP_NAME=' . envValue($name), true],
    ],
    '.env' => [
        ['/^APP_NAME=.*$/m', 'APP_NAME=' . envValue($name), true],
    ],
    'README.md' => [
        ['# Laravel API Starter', "# $name", false],
        ['laravel-api-starter', $slug, false],
    ],
    'docker-compose.yml' => [
        ['laravel-api-starter', $slug, false],
    ],
];

// --- Apply -------------------------------------------------------------------

line("");
$changedFiles = 0;

foreach ($plan as $relPath => $rules) {
    $path = "$root/$relPath";

    if (! is_file($path)) {
        line(C_DIM . "  skip  $relPath (not found)" . C_RESET);
        continue;
    }

    $original = file_get_contents($path);
    $contents = $original;
    $hits = 0;

    foreach ($rules as [$search, $replace, $isRegex]) {
        if ($isRegex) {
            $contents = preg_replace($search, $replace, $contents, -1, $count);
            $hits += $count;
        } else {
            $count = substr_count($contents, $search);
            if ($count > 0) {
                $contents = str_replace($search, $replace, $contents);
                $hits += $count;
            }
        }
    }

    if ($contents === $original) {
        line(C_DIM . "  ----  $relPath (no placeholders found)" . C_RESET);
        continue;
    }

    if (! $dryRun) {
        file_put_contents($path, $contents);
    }

    $changedFiles++;
    line(C_GREEN . "  ok    " . C_RESET . "$relPath " . C_DIM . "($hits replacement" . ($hits === 1 ? '' : 's') . ")" . C_RESET);
}

// --- Finish ------------------------------------------------------------------

line("");
if ($dryRun) {
    line(C_YELLOW . "  Dry run complete — $changedFiles file(s) would change.\n" . C_RESET);
    exit(0);
}

line(C_GREEN . C_BOLD . "  Done — updated $changedFiles file(s).\n" . C_RESET);

line("  Next steps:");
line(C_DIM . "    composer install" . C_RESET);
line(C_DIM . "    cp .env.example .env   # if you don't have .env yet" . C_RESET);
line(C_DIM . "    php artisan key:generate" . C_RESET);
line(C_DIM . "    php artisan migrate --seed" . C_RESET);
line("");

$selfDestruct = isset($args['self-destruct']);
if (! $selfDestruct && $interactive) {
    $selfDestruct = confirm('Remove this init script now that setup is complete?', false);
}

if ($selfDestruct) {
    @unlink(__FILE__);
    $dir = __DIR__;
    if (is_dir($dir) && count(scandir($dir)) === 2) {
        @rmdir($dir);
    }
    line(C_GREEN . "  Removed the init script.\n" . C_RESET);
}

exit(0);

// --- Helpers -----------------------------------------------------------------

/**
 * @return array<string, string|true>
 */
function parseArgs(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (! str_starts_with($arg, '-')) {
            continue;
        }
        $arg = ltrim($arg, '-');
        if (str_contains($arg, '=')) {
            [$k, $v] = explode('=', $arg, 2);
            $out[$k] = trim($v, "\"'");
        } else {
            $out[$arg] = true;
        }
    }

    return $out;
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function envValue(string $name): string
{
    // Quote when the value contains characters that would break dotenv parsing.
    if (preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
        return $name;
    }

    return '"' . str_replace('"', '\"', $name) . '"';
}

function defaultVendor(): string
{
    $user = @shell_exec('git config user.name 2>/dev/null');
    $user = is_string($user) ? slugify($user) : '';

    return $user !== '' ? $user : 'app';
}

function ask(string $question, string $default): string
{
    fwrite(STDOUT, "  $question " . C_DIM . "[$default]" . C_RESET . ": ");
    $answer = fgets(STDIN);
    $answer = $answer === false ? '' : trim($answer);

    return $answer === '' ? $default : $answer;
}

function confirm(string $question, bool $default): bool
{
    $hint = $default ? 'Y/n' : 'y/N';
    fwrite(STDOUT, "  $question " . C_DIM . "[$hint]" . C_RESET . ": ");
    $answer = fgets(STDIN);
    $answer = $answer === false ? '' : strtolower(trim($answer));

    if ($answer === '') {
        return $default;
    }

    return in_array($answer, ['y', 'yes'], true);
}

function summary(string $label, string $value): void
{
    line("    " . C_DIM . str_pad($label, 18) . C_RESET . C_BOLD . $value . C_RESET);
}

function line(string $text): void
{
    fwrite(STDOUT, $text . "\n");
}

function fail(string $message): never
{
    fwrite(STDERR, C_RED . "  Error: $message\n" . C_RESET);
    exit(1);
}

function usage(): void
{
    $help = <<<TXT

  {b}Initialize a project from this template.{r}

  {b}Usage{r}
    php scripts/init-project.php [options]

  {b}Options{r}
    --name="..."          Application name (APP_NAME, README title).
    --slug=...            Kebab-case project slug (composer package, docker volume).
    --vendor=...          Composer vendor (the part before "/").
    --description="..."   Composer package description.
    --dry-run             Show what would change without writing.
    --no-interaction      Don't prompt; use flags/defaults (requires --name).
    --self-destruct       Delete this script when finished.
    -h, --help            Show this help.

  {d}Interactive by default — just run it with no options.{r}

  TXT;

    line(strtr($help, ['{b}' => C_BOLD, '{r}' => C_RESET, '{d}' => C_DIM]));
}
