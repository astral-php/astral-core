<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;
use Core\Console\Input;

/**
 * Commande : serve
 *
 * Lance le serveur de développement PHP intégré sur public/.
 *
 *   php astral serve
 *   php astral serve --port=8085
 *   php astral serve --host=127.0.0.1 --port=8085
 */
final class ServeCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath) {}

    #[\Override]
    public function getName(): string
    {
        return 'serve';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Lance le serveur de développement PHP (public/)';
    }

    #[\Override]
    public function execute(array $args, Console $console): int
    {
        $input = new Input($args);

        if ($input->wantsHelp()) {
            $this->showHelp($console);
            return 0;
        }

        $unknown = $input->unknownOptions(['host', 'port', 'help', 'h']);
        if ($unknown !== []) {
            $console->error('Option(s) inconnue(s) : ' . implode(', ', array_map(
                static fn(string $n): string => strlen($n) === 1 ? "-{$n}" : "--{$n}",
                $unknown,
            )));
            return 1;
        }

        $host = $input->optionString('host', '127.0.0.1') ?? '127.0.0.1';
        $portRaw = $input->optionString('port', '8082') ?? '8082';

        if (!preg_match('/^[A-Za-z0-9.:\\[\\]-]+$/', $host)) {
            $console->error("Hôte invalide : {$host}");
            return 1;
        }

        if (!ctype_digit($portRaw)) {
            $console->error("Port invalide : {$portRaw}");
            return 1;
        }
        $port = (int) $portRaw;
        if ($port < 1 || $port > 65535) {
            $console->error("Port hors plage 1–65535 : {$port}");
            return 1;
        }

        $public = $this->basePath . DIRECTORY_SEPARATOR . 'public';
        $index  = $public . DIRECTORY_SEPARATOR . 'index.php';
        $router = $public . DIRECTORY_SEPARATOR . 'router.php';
        $autoload = $this->basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!is_file($autoload)) {
            $console->error('vendor/autoload.php introuvable — lancez composer install.');
            return 1;
        }
        if (!is_file($index)) {
            $console->error('public/index.php introuvable.');
            return 1;
        }
        if (!is_file($router)) {
            $console->error('public/router.php introuvable — requis pour serve.');
            return 1;
        }

        $php = PHP_BINARY;
        if ($php === '' || !is_file($php)) {
            $console->error('Binaire PHP CLI introuvable (PHP_BINARY).');
            return 1;
        }

        if ($this->isPortInUse($host, $port)) {
            $console->error("Le port {$port} est déjà utilisé sur {$host}.");
            return 1;
        }

        $address = "{$host}:{$port}";
        $console->success('Serveur de développement Astral');
        $console->writeln("  PHP     : " . PHP_VERSION);
        $console->writeln("  Public  : {$public}");
        $console->writeln("  URL     : http://{$address}");
        $console->writeln('');
        $console->info('Ctrl+C pour arrêter.');
        $console->writeln('');

        $cmd = [
            $php,
            '-S',
            $address,
            '-t',
            $public,
            $router,
        ];

        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $this->basePath);
        if (!is_resource($process)) {
            $console->error('Impossible de démarrer le serveur PHP.');
            return 1;
        }

        $status = proc_close($process);

        return $status === -1 ? 1 : $status;
    }

    private function showHelp(Console $console): void
    {
        $console->writeln('');
        $console->writeln('<info>serve</info> — ' . $this->getDescription());
        $console->writeln('');
        $console->writeln('Usage :');
        $console->writeln('  <comment>php astral serve [--host=127.0.0.1] [--port=8082]</comment>');
        $console->writeln('');
        $console->writeln('Options :');
        $console->writeln('  <comment>--host</comment>  Adresse d\'écoute (défaut 127.0.0.1)');
        $console->writeln('  <comment>--port</comment>  Port (défaut 8082)');
        $console->writeln('');
    }

    private function isPortInUse(string $host, int $port): bool
    {
        $bindHost = $host;
        if ($host === '0.0.0.0') {
            $bindHost = '127.0.0.1';
        }

        $errno  = 0;
        $errstr = '';
        $socket = @fsockopen($bindHost, $port, $errno, $errstr, 0.3);
        if (is_resource($socket)) {
            fclose($socket);
            return true;
        }

        return false;
    }
}
