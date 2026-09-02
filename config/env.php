<?php

function onefitEnv(string $key, ?string $default = null): ?string
{
    static $loaded = false;

    if (!$loaded) {
        $loaded = true;
        $path = dirname(__DIR__) . '/.env';

        if (is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if ($name === '') {
                    continue;
                }

                if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    $value = substr($value, 1, -1);
                }

                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }

    $value = getenv($key);
    return $value === false ? $default : $value;
}
