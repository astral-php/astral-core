<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Mini console CLI Astral.
 *
 * Enregistre des commandes et les dispatche selon $argv[1].
 * Fournit des helpers d'affichage ANSI et de saisie interactive.
 */
final class Console
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    private bool $ansi = true;

    private bool $interactive = true;

    public function __construct(private readonly string $appName = 'Astral MVC Console') {}

    public function setAnsi(bool $ansi): self
    {
        $this->ansi = $ansi;
        return $this;
    }

    public function setInteractive(bool $interactive): self
    {
        $this->interactive = $interactive;
        return $this;
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    // -------------------------------------------------------------------------
    // Enregistrement
    // -------------------------------------------------------------------------

    public function register(CommandInterface $command): self
    {
        $name = $command->getName();
        if (isset($this->commands[$name])) {
            $this->warning("Commande \"{$name}\" déjà enregistrée — remplacement explicite.");
        }
        $this->commands[$name] = $command;
        return $this;
    }

    /** @return array<string, CommandInterface> */
    public function getCommands(): array
    {
        return $this->commands;
    }

    // -------------------------------------------------------------------------
    // Exécution
    // -------------------------------------------------------------------------

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $tokens = array_values(array_slice($argv, 1));

        // Options globales avant le nom de commande
        $global = [];
        while ($tokens !== [] && str_starts_with($tokens[0], '-')) {
            $global[] = array_shift($tokens);
        }

        $globalInput = new Input($global);
        if ($globalInput->hasOption('no-ansi')) {
            $this->setAnsi(false);
        }
        if ($globalInput->hasOption('no-interaction')) {
            $this->setInteractive(false);
        }

        $name = $tokens[0] ?? 'list';
        $args = array_values(array_slice($tokens, 1));

        if ($name === 'list' || $name === '--help' || $name === '-h') {
            $this->showList();
            return 0;
        }

        // php astral --help make:module  (rare) déjà couvert ; make:module --help :
        $argInput = new Input($args);
        if ($argInput->wantsHelp()) {
            if (!isset($this->commands[$name])) {
                $this->error("Commande inconnue : \"{$name}\"");
                return 1;
            }
            $this->showCommandHelp($this->commands[$name]);
            return 0;
        }

        if (!isset($this->commands[$name])) {
            $this->error("Commande inconnue : \"{$name}\"");
            $this->writeln('  Utilisez <comment>list</comment> pour voir les commandes disponibles.');
            return 1;
        }

        try {
            return $this->commands[$name]->execute($args, $this);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if ($globalInput->hasOption('verbose') || $globalInput->hasOption('v')) {
                $this->writeln($e->getFile() . ':' . $e->getLine());
            }
            return 1;
        }
    }

    // -------------------------------------------------------------------------
    // Affichage
    // -------------------------------------------------------------------------

    public function writeln(string $text = ''): void
    {
        echo $this->colorize($text) . PHP_EOL;
    }

    public function write(string $text): void
    {
        echo $this->colorize($text);
    }

    public function success(string $text): void
    {
        $this->writeln($this->ansi ? "\033[32m✓ {$text}\033[0m" : "OK {$text}");
    }

    public function error(string $text): void
    {
        $line = $this->ansi ? "\033[31m✗ {$text}\033[0m" : "ERROR {$text}";
        fwrite(STDERR, $line . PHP_EOL);
    }

    public function info(string $text): void
    {
        $this->writeln($this->ansi ? "\033[36m→ {$text}\033[0m" : "-> {$text}");
    }

    public function warning(string $text): void
    {
        $line = $this->ansi ? "\033[33m⚠ {$text}\033[0m" : "WARN {$text}";
        fwrite(STDERR, $line . PHP_EOL);
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
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
        $this->writeln($this->ansi ? "\033[1m{$line}\033[0m" : $line);
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
    // Saisie interactive
    // -------------------------------------------------------------------------

    public function ask(string $question, string $default = ''): string
    {
        if (!$this->interactive) {
            if ($default !== '') {
                return $default;
            }
            throw new \RuntimeException("Saisie requise pour \"{$question}\" (--no-interaction).");
        }

        $hint = $default !== '' ? ($this->ansi ? " \033[90m[{$default}]\033[0m" : " [{$default}]") : '';
        $prefix = $this->ansi ? "\033[36m? {$question}{$hint} : \033[0m" : "? {$question}{$hint} : ";
        $this->write($prefix);
        $input = fgets(STDIN);
        if ($input === false) {
            throw new \RuntimeException("Fin d'entrée standard pendant \"{$question}\".");
        }
        $input = trim($input);
        return $input === '' ? $default : $input;
    }

    public function confirm(string $question, bool $default = true): bool
    {
        if (!$this->interactive) {
            return $default;
        }

        $hint = $default ? 'O/n' : 'o/N';
        $prefix = $this->ansi
            ? "\033[36m? {$question} \033[90m[{$hint}]\033[0m : \033[0m"
            : "? {$question} [{$hint}] : ";
        $this->write($prefix);
        $input = fgets(STDIN);
        if ($input === false) {
            throw new \RuntimeException("Fin d'entrée standard pendant confirmation.");
        }
        $input = strtolower(trim($input));
        if ($input === '') {
            return $default;
        }
        return in_array($input, ['o', 'oui', 'y', 'yes', '1'], true);
    }

    /**
     * @param array<string, string> $choices
     */
    public function choice(string $question, array $choices, string $default = ''): string
    {
        if (!$this->interactive) {
            if ($default !== '' && isset($choices[$default])) {
                return $default;
            }
            throw new \RuntimeException("Choix requis pour \"{$question}\" (--no-interaction).");
        }

        $this->writeln($this->ansi ? "\033[36m? {$question}\033[0m" : "? {$question}");
        foreach ($choices as $key => $label) {
            $marker = $key === $default ? ($this->ansi ? "\033[32m▶\033[0m" : '>') : ' ';
            $keyFmt = $this->ansi ? "\033[33m[{$key}]\033[0m" : "[{$key}]";
            $this->writeln("  {$marker} {$keyFmt} {$label}");
        }
        $hint = $default !== '' ? ($this->ansi ? " \033[90m[{$default}]\033[0m" : " [{$default}]") : '';
        $this->write("  Votre choix{$hint} : ");
        $input = fgets(STDIN);
        if ($input === false) {
            throw new \RuntimeException("Fin d'entrée standard pendant choix.");
        }
        $input = trim($input);
        $input = $input === '' ? $default : $input;
        return isset($choices[$input]) ? $input : $default;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function showList(): void
    {
        $this->writeln('');
        $this->writeln($this->ansi ? "\033[1m{$this->appName}\033[0m" : $this->appName);
        $this->writeln('');
        $this->writeln('Commandes disponibles :');
        $this->writeln('');

        foreach ($this->commands as $command) {
            $name = $command->getName();
            $desc = $command->getDescription();
            if ($this->ansi) {
                printf("  \033[32m%-30s\033[0m %s\n", $name, $desc);
            } else {
                printf("  %-30s %s\n", $name, $desc);
            }
        }

        $this->writeln('');
        $this->writeln('Aide : <comment>php astral</comment> <commande> <comment>--help</comment>');
        $this->writeln('');
    }

    private function showCommandHelp(CommandInterface $command): void
    {
        $this->writeln('');
        $this->writeln('<info>' . $command->getName() . '</info> — ' . $command->getDescription());
        $this->writeln('');
        $this->writeln('Usage :');
        $this->writeln('  <comment>php astral ' . $command->getName() . ' [arguments] [options]</comment>');
        $this->writeln('');
        $this->writeln('Options communes : <comment>--help</comment>, <comment>--no-interaction</comment>, <comment>--no-ansi</comment>');
        $this->writeln('');
    }

    private function colorize(string $text): string
    {
        if (!$this->ansi) {
            return str_replace(
                ['<info>', '</info>', '<comment>', '</comment>', '<error>', '</error>'],
                ['', '', '', '', '', ''],
                $text,
            );
        }

        return str_replace(
            ['<info>', '</info>', '<comment>', '</comment>', '<error>', '</error>'],
            ["\033[36m", "\033[0m", "\033[33m", "\033[0m", "\033[31m", "\033[0m"],
            $text,
        );
    }
}
