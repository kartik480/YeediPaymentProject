<?php
declare(strict_types=1);
final class Env {
    private static array $values = [];
    public static function load(string $path): void {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k,$v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if (($v[0] ?? '') === '"' && str_ends_with($v, '"')) $v = substr($v,1,-1);
            elseif (($v[0] ?? '') === "'" && str_ends_with($v, "'")) $v = substr($v,1,-1);
            self::$values[$k] = $v;
            if (getenv($k) === false) putenv($k.'='.$v);
        }
    }
    public static function get(string $key, ?string $default=null): ?string {
        $v = getenv($key);
        if ($v !== false) return $v;
        return self::$values[$key] ?? $default;
    }
    public static function bool(string $key, bool $default=false): bool {
        $v = self::get($key, $default ? 'true' : 'false');
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
    public static function int(string $key, int $default=0): int { return (int)(self::get($key, (string)$default)); }
}
