<?php

declare(strict_types=1);

namespace Core\Dumper;

/**
 * Moteur de rendu lisible pour dump() / dd().
 * HTML (navigateur) ou ANSI (CLI), zéro dépendance.
 */
final class Dumper
{
    public static function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            self::isCli()
                ? self::renderCli($var)
                : self::renderHtml($var);
        }
    }

    public static function dd(mixed ...$vars): never
    {
        self::dump(...$vars);
        exit(1);
    }

    // ── Rendu HTML ────────────────────────────────────────────────────────────

    private static function renderHtml(mixed $var): void
    {
        $content = self::format($var);
        $trace   = self::callerTrace();

        echo <<<HTML
        <style>
          .astral-dump{font-family:'JetBrains Mono',ui-monospace,monospace;font-size:13px;
            background:#1e1e2e;color:#cdd6f4;border-left:4px solid #89b4fa;
            padding:12px 16px;margin:8px 0;border-radius:4px;white-space:pre-wrap;
            overflow:auto;line-height:1.6}
          .astral-dump-meta{color:#6c7086;font-size:11px;margin-bottom:8px;
            padding-bottom:6px;border-bottom:1px solid #313244}
          .astral-dump-str{color:#a6e3a1}
          .astral-dump-int,.astral-dump-float{color:#fab387}
          .astral-dump-bool{color:#cba6f7}
          .astral-dump-null{color:#f38ba8}
          .astral-dump-key{color:#89b4fa}
          .astral-dump-type{color:#6c7086;font-size:11px}
        </style>
        <div class="astral-dump">
          <div class="astral-dump-meta">{$trace}</div>{$content}
        </div>
        HTML;
    }

    private static function format(mixed $var, int $depth = 0): string
    {
        return match (true) {
            is_null($var)   => '<span class="astral-dump-null">null</span>',
            is_bool($var)   => '<span class="astral-dump-bool">'
                               . ($var ? 'true' : 'false')
                               . '</span>',
            is_int($var)    => '<span class="astral-dump-int">' . $var . '</span>'
                               . ' <span class="astral-dump-type">int</span>',
            is_float($var)  => '<span class="astral-dump-float">' . $var . '</span>'
                               . ' <span class="astral-dump-type">float</span>',
            is_string($var) => '<span class="astral-dump-str">"'
                               . htmlspecialchars($var, ENT_QUOTES, 'UTF-8')
                               . '"</span>'
                               . ' <span class="astral-dump-type">string(' . strlen($var) . ')</span>',
            is_array($var)  => self::formatArray($var, $depth),
            is_object($var) => self::formatObject($var, $depth),
            default         => htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8'),
        };
    }

    /** @param array<mixed> $arr */
    private static function formatArray(array $arr, int $depth): string
    {
        if ($arr === []) {
            return '<span class="astral-dump-type">array(0) []</span>';
        }

        $indent = str_repeat('  ', $depth + 1);
        $lines  = ['<span class="astral-dump-type">array(' . count($arr) . ')</span> ['];

        foreach ($arr as $k => $v) {
            $key     = '<span class="astral-dump-key">' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '</span>';
            $lines[] = $indent . $key . ' => ' . self::format($v, $depth + 1);
        }

        $lines[] = str_repeat('  ', $depth) . ']';

        return implode("\n", $lines);
    }

    private static function formatObject(object $obj, int $depth): string
    {
        $class  = get_class($obj);
        $props  = (array) $obj;
        $indent = str_repeat('  ', $depth + 1);
        $lines  = ['<span class="astral-dump-bool">' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '</span> {'];

        foreach ($props as $k => $v) {
            $k       = (string) preg_replace('/\x00.+\x00/', '', (string) $k);
            $key     = '<span class="astral-dump-key">' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</span>';
            $lines[] = $indent . $key . ': ' . self::format($v, $depth + 1);
        }

        $lines[] = str_repeat('  ', $depth) . '}';

        return implode("\n", $lines);
    }

    // ── Rendu CLI ─────────────────────────────────────────────────────────────

    private static function renderCli(mixed $var): void
    {
        $trace = self::callerTrace();
        echo "\033[90m{$trace}\033[0m\n";
        echo self::formatCli($var, 0) . "\n\n";
    }

    private static function formatCli(mixed $var, int $depth): string
    {
        return match (true) {
            is_null($var)   => "\033[31mnull\033[0m",
            is_bool($var)   => "\033[35m" . ($var ? 'true' : 'false') . "\033[0m",
            is_int($var)    => "\033[33m{$var}\033[0m \033[90mint\033[0m",
            is_float($var)  => "\033[33m{$var}\033[0m \033[90mfloat\033[0m",
            is_string($var) => "\033[32m\"{$var}\"\033[0m \033[90mstring(" . strlen($var) . ")\033[0m",
            is_array($var)  => self::formatCliArray($var, $depth),
            is_object($var) => "\033[35m" . get_class($var) . "\033[0m " . print_r($var, true),
            default         => print_r($var, true),
        };
    }

    /** @param array<mixed> $arr */
    private static function formatCliArray(array $arr, int $depth): string
    {
        if ($arr === []) {
            return "\033[90marray(0) []\033[0m";
        }

        $indent = str_repeat('  ', $depth + 1);
        $lines  = ["\033[90marray(" . count($arr) . ")\033[0m ["];

        foreach ($arr as $k => $v) {
            $lines[] = $indent . "\033[34m{$k}\033[0m => " . self::formatCli($v, $depth + 1);
        }

        $lines[] = str_repeat('  ', $depth) . ']';

        return implode("\n", $lines);
    }

    // ── Utilitaires ───────────────────────────────────────────────────────────

    private static function callerTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $root  = defined('BASE_PATH')
            ? BASE_PATH
            : dirname(__DIR__, 3); // src/Core/Dumper → racine projet

        foreach ($trace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            if (str_contains($frame['file'], 'Dumper.php')) {
                continue;
            }
            if (str_contains($frame['file'], 'helpers.php')) {
                continue;
            }

            $file = str_replace([$root . '/', $root . '\\'], '', $frame['file']);

            return $file . ':' . ($frame['line'] ?? '?');
        }

        return 'unknown';
    }

    private static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }
}
