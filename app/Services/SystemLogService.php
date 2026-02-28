<?php

declare(strict_types=1);

namespace App\Services;

final class SystemLogService
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function getAccessLogs(int $limit = 50): array
    {
        $file = $this->resolveLogFile('access');
        if ($file === null) {
            return [];
        }

        $lines = $this->tail($file, $limit);
        $parsed = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            $record = $this->parseJsonLine($line);
            if ($record !== null) {
                $ctx = is_array($record['context'] ?? null) ? $record['context'] : [];
                $parsed[] = [
                    'date' => (string) ($record['datetime'] ?? 'unknown'),
                    'level' => (string) ($record['level_name'] ?? 'INFO'),
                    'request_id' => $ctx['request_id'] ?? null,
                    'user_id' => $ctx['user_id'] ?? null,
                    'method' => $ctx['method'] ?? 'unknown',
                    'path' => $ctx['path'] ?? 'unknown',
                    'status' => $ctx['status'] ?? 'unknown',
                    'duration_ms' => $ctx['duration_ms'] ?? null,
                    'ip_hash' => $ctx['ip_hash'] ?? null,
                    'user_agent' => $ctx['user_agent'] ?? null,
                    'context' => $ctx['context'] ?? [],
                    'raw' => $line,
                ];
                continue;
            }

            // Legacy formatter fallback.
            preg_match('/^\[(?P<date>.*?)\]\s+(?P<channel>.*?)\.(?P<level>.*?):\s+(?P<message>.*?)\s+(?P<context>\{.*?\})\s+(?P<extra>\[.*?\])$/', $line, $matches);
            if (!isset($matches['context'])) {
                $parsed[] = ['date' => 'unknown', 'raw' => $line];
                continue;
            }

            $ctx = json_decode($matches['context'], true) ?: [];
            $parsed[] = [
                'date' => $matches['date'] ?? 'unknown',
                'level' => $matches['level'] ?? 'INFO',
                'request_id' => $ctx['request_id'] ?? null,
                'user_id' => $ctx['user_id'] ?? null,
                'method' => $ctx['method'] ?? 'unknown',
                'path' => $ctx['path'] ?? 'unknown',
                'status' => $ctx['status'] ?? 'unknown',
                'duration_ms' => $ctx['duration_ms'] ?? null,
                'ip_hash' => $ctx['ip_hash'] ?? null,
                'user_agent' => $ctx['user_agent'] ?? null,
                'context' => $ctx['context'] ?? [],
                'raw' => $line,
            ];
        }

        return array_reverse($parsed); // Newest first
    }

    public function getErrorLogs(int $limit = 50): array
    {
        $file = $this->resolveLogFile('error');
        if ($file === null) {
            return [];
        }

        $lines = $this->tail($file, $limit);
        $parsed = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            $record = $this->parseJsonLine($line);
            if ($record !== null) {
                $ctx = is_array($record['context'] ?? null) ? $record['context'] : [];
                $parsed[] = [
                    'date' => (string) ($record['datetime'] ?? 'unknown'),
                    'level' => (string) ($record['level_name'] ?? 'ERROR'),
                    'request_id' => $ctx['request_id'] ?? null,
                    'user_id' => $ctx['user_id'] ?? null,
                    'method' => $ctx['method'] ?? null,
                    'path' => $ctx['path'] ?? null,
                    'status' => $ctx['status'] ?? null,
                    'ip_hash' => $ctx['ip_hash'] ?? null,
                    'user_agent' => $ctx['user_agent'] ?? null,
                    'message' => $record['message'] ?? $line,
                    'context' => $ctx['context'] ?? [],
                    'raw' => $line,
                ];
                continue;
            }

            preg_match('/^\[(?P<date>.*?)\]\s+(?P<channel>.*?)\.(?P<level>.*?):\s+(?P<message>.*?)(\s+\{.*?\})?(\s+\[.*?\])?$/', $line, $matches);
            $parsed[] = [
                'date' => $matches['date'] ?? 'unknown',
                'level' => $matches['level'] ?? 'ERROR',
                'message' => $matches['message'] ?? $line,
                'raw' => $line
            ];
        }

        return array_reverse($parsed);
    }

    /**
     * Efficiently read the last N lines of a file.
     */
    private function tail(string $filename, int $lines = 50): array
    {
        $handle = fopen($filename, "r");
        if (!$handle) return [];

        $lineCounter = $lines;
        $pos = -2; // Skip potential trailing newline
        $beginning = false;
        $text = [];

        while ($lineCounter > 0) {
            $t = "";
            while ($t !== "
") {
                if (fseek($handle, $pos, SEEK_END) === -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }

            $lineCounter--;
            if ($beginning) {
                rewind($handle);
            }
            
            $line = fgets($handle);
            if ($line !== false) {
                $text[] = trim($line);
            }

            if ($beginning) break;
        }

        fclose($handle);
        return $text;
    }

    private function parseJsonLine(string $line): ?array
    {
        $decoded = json_decode($line, true);
        if (!is_array($decoded) || !isset($decoded['datetime'])) {
            return null;
        }

        return $decoded;
    }

    private function resolveLogFile(string $channel): ?string
    {
        $base = rtrim($this->logPath, '/');
        $current = $base . '/' . $channel . '.log';
        $dated = glob($base . '/' . $channel . '-*.log') ?: [];

        $candidates = [];
        if (is_file($current)) {
            $candidates[] = $current;
        }
        foreach ($dated as $file) {
            if (is_file($file)) {
                $candidates[] = $file;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        return $candidates[0] ?? null;
    }
}
