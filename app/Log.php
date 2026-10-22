<?php
declare(strict_types=1);

final class Log {
    public static function write(string $channel, string $event, array $context = []): void {
        $dir = YEEDIPAY_ROOT.'/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $line = gmdate('c').' '.$channel.' '.$event.' '.json_encode($context, JSON_UNESCAPED_SLASHES);
        @file_put_contents($dir.'/app.log', $line.PHP_EOL, FILE_APPEND);
    }

    public static function safeThrowable(Throwable $e): string {
        return $e->getMessage();
    }
}
