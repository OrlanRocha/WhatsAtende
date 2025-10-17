<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

class FileLogger
{
    private string $path;

    public function __construct(string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->path = $path;
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $this->normalizeContext($context),
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false) {
            $encoded = json_encode([
                'timestamp' => $entry['timestamp'],
                'level' => strtoupper($level),
                'message' => $message,
                'context' => 'unserializable_context',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        file_put_contents($this->path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function normalizeContext(array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            return [
                'exception' => $value::class,
                'message' => $value->getMessage(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->normalizeValue($item);
            }

            return $result;
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : $value::class;
        }

        if (is_string($value) && strlen($value) > 2048) {
            return substr($value, 0, 2048) . '...';
        }

        return $value;
    }
}
