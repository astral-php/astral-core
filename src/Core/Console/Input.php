<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Lecture commune des arguments positionnels et options CLI.
 *
 * Formats acceptés :
 *   --flag
 *   --key=value
 *   --key value
 *   -h / -v (flags courts sans valeur)
 */
final class Input
{
    /** @var list<string> */
    private array $arguments;

    /** @var array<string, string|bool> */
    private array $options;

    /**
     * @param list<string> $argvSlice  Tokens après le nom de commande
     */
    public function __construct(array $argvSlice)
    {
        $arguments = [];
        $options   = [];
        $count     = count($argvSlice);

        for ($i = 0; $i < $count; $i++) {
            $token = $argvSlice[$i];

            if ($token === '--') {
                $arguments = array_merge($arguments, array_slice($argvSlice, $i + 1));
                break;
            }

            if (str_starts_with($token, '--')) {
                $body = substr($token, 2);
                if ($body === '') {
                    continue;
                }
                if (str_contains($body, '=')) {
                    [$key, $value] = explode('=', $body, 2);
                    $options[$key] = $value;
                    continue;
                }
                $next = $argvSlice[$i + 1] ?? null;
                if ($next !== null && !str_starts_with($next, '-')) {
                    $options[$body] = $next;
                    $i++;
                    continue;
                }
                $options[$body] = true;
                continue;
            }

            if (preg_match('/^-([a-zA-Z])$/', $token, $m) === 1) {
                $options[$m[1]] = true;
                continue;
            }

            $arguments[] = $token;
        }

        $this->arguments = $arguments;
        $this->options   = $options;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    public function option(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }

    public function optionString(string $name, ?string $default = null): ?string
    {
        if (!$this->hasOption($name)) {
            return $default;
        }
        $value = $this->options[$name];
        if ($value === true) {
            return $default;
        }

        return (string) $value;
    }

    public function wantsHelp(): bool
    {
        return $this->hasOption('help') || $this->hasOption('h');
    }

    /**
     * @param list<string> $allowed  Noms d'options autorisés (sans tirets)
     * @return list<string>          Options inconnues détectées
     */
    public function unknownOptions(array $allowed): array
    {
        $allowedMap = array_fill_keys($allowed, true);
        $unknown    = [];
        foreach (array_keys($this->options) as $name) {
            if (!isset($allowedMap[$name])) {
                $unknown[] = $name;
            }
        }

        return $unknown;
    }
}
