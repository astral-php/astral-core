<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Mini console CLI.
 *
 * Enregistre des commandes et les dispatche selon l'argument $argv[1].
 * Fournit des helpers d'affichage ANSI et de saisie interactive (ask/confirm/choice).
 *
 * Usage dans bin/console :
 *   $console = new Console('Astral MVC');
 *   $console->register(new ClearCacheCommand(...));
 *   exit($console->run($_SERVER['argv'] ?? []));
 */
final class Console
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    public function __construct(private string $appName = 'Astral MVC Console') {}

    // -------------------------------------------------------------------------
    // Enregistrement
    // -------------------------------------------------------------------------

    public function register(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Exécution
    // -------------------------------------------------------------------------

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? 'list';
        $args = array_slice($argv, 2);

        if ($name === 'list' || $name === '--help' || $name === '-h') {
            $this->showList();
            return 0;
        }

        if (!isset($this->commands[$name])) {
            $this->error("Commande inconnue : \"{$name}\"");
            $this->writeln("  Utilisez <comment>list</comment> pour voir les commandes disponibles.");
            return 1;
        }

        return $this->commands[$name]->execute($args, $this);
    }

    // -------------------------------------------------------------------------
    // Affichage
    // -------------------------------------------------------------------------

    public function writeln(string $text = ''): void
    {
        echo $this->colorize($text) . PHP_EOL;
    }

    public function success(string $text): void
    {
        echo "\033[32m✓ {$text}\033[0m" . PHP_EOL;
    }

    public function error(string $text): void
    {
        echo "\033[31m✗ {$text}\033[0m" . PHP_EOL;
    }

    public function info(string $text): void
    {
        echo "\033[36m→ {$text}\033[0m" . PHP_EOL;
    }

    public function warning(string $text): void
    {
        echo "\033[33m⚠ {$text}\033[0m" . PHP_EOL;
    }

    /**
     * Affiche un tableau aligné.
     *
     * @param list<string>         $headers
     * @param list<list<string>>   $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = array_map('mb_strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $cell));
            }
        }

        $separator = '+' . implode('+', array_map(fn($w) => str_repeat('-', $w + 2), $widths)) . '+';

        $this->writeln($separator);
        $line = '|';
        foreach ($headers as $i => $h) {
            $line .= ' ' . str_pad($h, $widths[$i]) . ' |';
        }
        $this->writeln("\033[1m{$line}\033[0m");
        $this->writeln($separator);

        foreach ($rows as $row) {
            $line = '|';
            foreach ($row as $i => $cell) {
                $line .= ' ' . str_pad((string) $cell, $widths[$i]) . ' |';
            }
            $this->writeln($line);
        }

        $this->writeln($separator);
    }

    // -------------------------------------------------------------------------
    // Saisie interactive (stdin)
    // -------------------------------------------------------------------------

    /**
     * Affiche un texte sans saut de ligne (pour les prompts inline).
     */
    public function write(string $text): void
    {
        echo $this->colorize($text);
    }

    /**
     * Pose une question ouverte et retourne la saisie (ou la valeur par défaut).
     */
    public function ask(string $question, string $default = ''): string
    {
        $hint = $default !== '' ? " \033[90m[{$default}]\033[0m" : '';
        $this->write("\033[36m? {$question}{$hint} : \033[0m");
        $input = trim((string) fgets(STDIN));
        return $input === '' ? $default : $input;
    }

    /**
     * Pose une question Oui/Non et retourne un booléen.
     */
    public function confirm(string $question, bool $default = true): bool
    {
        $hint = $default ? 'O/n' : 'o/N';
        $this->write("\033[36m? {$question} \033[90m[{$hint}]\033[0m : \033[0m");
        $input = strtolower(trim((string) fgets(STDIN)));
        if ($input === '') {
            return $default;
        }
        return in_array($input, ['o', 'oui', 'y', 'yes', '1'], true);
    }

    /**
     * Affiche un menu de choix et retourne la clé sélectionnée.
     *
     * @param array<string, string> $choices  [clé => libellé]
     */
    public function choice(string $question, array $choices, string $default = ''): string
    {
        $this->writeln("\033[36m? {$question}\033[0m");
        foreach ($choices as $key => $label) {
            $marker = $key === $default ? "\033[32m▶\033[0m" : ' ';
            $this->writeln("  {$marker} \033[33m[{$key}]\033[0m {$label}");
        }
        $hint = $default !== '' ? " \033[90m[{$default}]\033[0m" : '';
        $this->write("  Votre choix{$hint} : ");
        $input = trim((string) fgets(STDIN));
        $input = $input === '' ? $default : $input;
        return isset($choices[$input]) ? $input : $default;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function showList(): void
    {
        $this->writeln('');
        $this->writeln("\033[1m{$this->appName}\033[0m");
        $this->writeln('');
        $this->writeln('Commandes disponibles :');
        $this->writeln('');

        foreach ($this->commands as $command) {
            printf("  \033[32m%-30s\033[0m %s\n", $command->getName(), $command->getDescription());
        }

        $this->writeln('');
    }

    /** Remplace les balises <info>, <comment>, <error> par des codes ANSI. */
    private function colorize(string $text): string
    {
        return str_replace(
            ['<info>', '</info>', '<comment>', '</comment>', '<error>', '</error>'],
            ['\033[36m', '\033[0m', '\033[33m', '\033[0m', '\033[31m', '\033[0m'],
            $text,
        );
    }
}
