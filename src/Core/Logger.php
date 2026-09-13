<?php

declare(strict_types=1);

namespace Core;

/**
 * Logger fichier minimaliste (inspiré PSR-3).
 *
 * Écrit une ligne par entrée dans storage/logs/YYYY-MM-DD.log.
 * Le répertoire de logs est créé automatiquement si absent.
 *
 * Niveaux : debug, info, warning, error
 */
final class Logger
{
    public function __construct(private string $logDir) {}

    // -------------------------------------------------------------------------
    // API publique
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    // -------------------------------------------------------------------------
    // Écriture
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        $date    = date('Y-m-d');
        $time    = date('Y-m-d H:i:s');
        $file    = $this->logDir . DIRECTORY_SEPARATOR . "{$date}.log";
        $ctx     = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line    = "[{$time}] {$level}: {$message}{$ctx}" . PHP_EOL;

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
