<?php
declare(strict_types=1);

final class Env {
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $path): void {
        if (!is_file($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) return;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string {
        if (array_key_exists($key, self::$values)) return self::$values[$key];
        $env = getenv($key);
        if ($env !== false) return (string)$env;
        return $default;
    }
}
