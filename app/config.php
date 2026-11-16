<?php
// Reads the .env file and makes values available through env('KEY').

function load_env(string $path): void
{
    if (!file_exists($path)) {
        die('.env file is missing. Copy .env.example to .env and fill in your values.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}
// Builds a link that works from any folder: url('login.php')
function url(string $path = ''): string
{
    return rtrim(env('BASE_URL', '/loyalty-rewards/public'), '/') . '/' . ltrim($path, '/');
}

load_env(__DIR__ . '/../.env');