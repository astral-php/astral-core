<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;

/**
 * Commande : make:controller
 *
 * Génère un contrôleur dans app/Controllers/.
 * Trois modes disponibles via flag :
 *   --resource  CRUD complet (index, show, create, store, edit, update, destroy)
 *   --api       Actions JSON (index, show, store, update, destroy)
 *   (aucun)     Contrôleur vide
 *
 * Détecte automatiquement le DAO correspondant dans app/Dao/.
 *
 * Usage :
 *   php bin/console make:controller Article
 *   php bin/console make:controller Article --resource
 *   php bin/console make:controller Article --api
 */
final class MakeControllerCommand implements CommandInterface
{
    public function __construct(
        private string $controllersPath,
        private string $daoPath,
    ) {}

    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Génère un contrôleur dans app/Controllers/ [--resource|--api]';
    }

    public function execute(array $args, Console $console): int
    {
        $name = $args[0] ?? null;

        if ($name === null || $name === '') {
            $console->error('Usage : make:controller <NomEnPascalCase> [--resource|--api]');
            $console->writeln('');
            $console->writeln('  Exemples :');
            $console->writeln('    <comment>php bin/console make:controller Article</comment>');
            $console->writeln('    <comment>php bin/console make:controller Article --resource</comment>');
            $console->writeln('    <comment>php bin/console make:controller Article --api</comment>');
            return 1;
        }

        $name = $this->toPascalCase($name);

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $console->error("Nom invalide : utilisez uniquement des lettres et chiffres (PascalCase).");
            return 1;
        }

        $isResource = in_array('--resource', $args, true);
        $isApi      = in_array('--api', $args, true);

        if (!is_dir($this->controllersPath)) {
            mkdir($this->controllersPath, 0755, true);
        }

        $path = $this->controllersPath . DIRECTORY_SEPARATOR . "{$name}Controller.php";

        if (file_exists($path)) {
            $console->warning("{$name}Controller existe déjà : app/Controllers/{$name}Controller.php");
            return 1;
        }

        $daoExists  = file_exists($this->daoPath . DIRECTORY_SEPARATOR . "{$name}Dao.php");
        $entityLower = lcfirst($name);
        $tableName   = $this->toSnakePlural($name);

        $stub = match (true) {
            $isApi      => $this->stubApi($name, $entityLower, $daoExists),
            $isResource => $this->stubResource($name, $entityLower, $tableName, $daoExists),
            default     => $this->stubEmpty($name),
        };

        file_put_contents($path, $stub);

        $type = match (true) {
            $isApi      => 'API (JSON)',
            $isResource => 'Resource (CRUD)',
            default     => 'Vide',
        };

        $console->success("Contrôleur créé : app/Controllers/{$name}Controller.php");
        $console->writeln("  Type  : <info>{$type}</info>");

        if (!$daoExists && ($isResource || $isApi)) {
            $console->warning("Le DAO App\\Dao\\{$name}Dao n'existe pas encore.");
            $console->writeln("  → Lancez : <comment>php bin/console make:dao {$name}</comment>");
        }

        $console->writeln("  → Enregistrez le contrôleur dans <comment>app/Providers/AppServiceProvider.php</comment>");
        $console->writeln("  → Déclarez vos routes dans <comment>config/routes.php</comment>");

        return 0;
    }

    // -------------------------------------------------------------------------
    // Stubs
    // -------------------------------------------------------------------------

    private function stubEmpty(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Controllers;

        use Controller\AbstractController;
        use Core\Http\Response;
        use Core\View;

        final class {$name}Controller extends AbstractController
        {
            public function __construct(View \$view)
            {
                parent::__construct(\$view);
            }

            public function index(): Response
            {
                return \$this->render('{$this->toViewPath($name)}/index', [
                    'title' => '{$name}',
                ]);
            }
        }
        PHP;
    }

    private function stubResource(string $name, string $entity, string $table, bool $daoExists): string
    {
        $daoUse     = $daoExists ? "\nuse App\\Dao\\{$name}Dao;" : '';
        $daoParam   = $daoExists ? "\n        private {$name}Dao \${$entity}Dao," : '';
        $daoMake    = $daoExists ? "\$c->make({$name}Dao::class)" : 'null /* TODO: injecter votre DAO */';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Controllers;
        {$daoUse}
        use Controller\AbstractController;
        use Core\Exception\NotFoundException;
        use Core\Http\Response;
        use Core\Request;
        use Core\Session;
        use Core\View;

        final class {$name}Controller extends AbstractController
        {
            public function __construct(
                View            \$view,
                private Request \$request,
                private Session \$session,{$daoParam}
            ) {
                parent::__construct(\$view);
            }

            /** GET /{$table} */
            public function index(): Response
            {
                \$page   = (int) \$this->request->query('page', 1);
                \$result = \$this->{$entity}Dao->paginate(page: \$page, perPage: 15);

                return \$this->render('{$this->toViewPath($name)}/index', [
                    'title'   => '{$name}',
                    'items'   => \$result['data'],
                    'pages'   => \$result['pages'],
                    'current' => \$result['current'],
                ]);
            }

            /** GET /{$table}/:id */
            public function show(string \$id): Response
            {
                \$item = \$this->{$entity}Dao->findById((int) \$id)
                    ?? throw new NotFoundException("{$name} #{" . "\$id} introuvable.");

                return \$this->render('{$this->toViewPath($name)}/show', [
                    'title' => "{$name} #{\$id}",
                    'item'  => \$item,
                ]);
            }

            /** GET /{$table}/create */
            public function create(): Response
            {
                return \$this->render('{$this->toViewPath($name)}/create', [
                    'title'  => 'Nouveau {$name}',
                    'errors' => [],
                    'old'    => [],
                ]);
            }

            /** POST /{$table} */
            public function store(): Response
            {
                \$data = \$this->request->post();

                \$v = \$this->validate(\$data, [
                    // TODO : définir vos règles de validation
                ]);

                if (\$v->fails()) {
                    return \$this->render('{$this->toViewPath($name)}/create', [
                        'title'  => 'Nouveau {$name}',
                        'errors' => \$v->errors(),
                        'old'    => \$data,
                    ]);
                }

                \$id = \$this->{$entity}Dao->insert([
                    // TODO : mapper \$data vers les colonnes
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                \$this->session->flash('success', '{$name} créé avec succès.');
                return \$this->redirect('/{$table}/' . \$id);
            }

            /** GET /{$table}/:id/edit */
            public function edit(string \$id): Response
            {
                \$item = \$this->{$entity}Dao->findById((int) \$id)
                    ?? throw new NotFoundException("{$name} #{" . "\$id} introuvable.");

                return \$this->render('{$this->toViewPath($name)}/edit', [
                    'title'  => "Modifier {$name} #{\$id}",
                    'item'   => \$item,
                    'errors' => [],
                ]);
            }

            /** PUT /{$table}/:id */
            public function update(string \$id): Response
            {
                \$data = \$this->request->post();

                \$v = \$this->validate(\$data, [
                    // TODO : définir vos règles de validation
                ]);

                if (\$v->fails()) {
                    \$item = \$this->{$entity}Dao->findById((int) \$id);
                    return \$this->render('{$this->toViewPath($name)}/edit', [
                        'title'  => "Modifier {$name} #{\$id}",
                        'item'   => \$item,
                        'errors' => \$v->errors(),
                    ]);
                }

                \$this->{$entity}Dao->update((int) \$id, [
                    // TODO : mapper \$data vers les colonnes
                ]);

                \$this->session->flash('success', '{$name} mis à jour.');
                return \$this->redirect('/{$table}/' . \$id);
            }

            /** DELETE /{$table}/:id */
            public function destroy(string \$id): Response
            {
                \$deleted = \$this->{$entity}Dao->delete((int) \$id);

                if (\$deleted === 0) {
                    throw new NotFoundException("{$name} #{" . "\$id} introuvable.");
                }

                \$this->session->flash('success', '{$name} supprimé.');
                return \$this->redirect('/{$table}');
            }
        }
        PHP;
    }

    private function stubApi(string $name, string $entity, bool $daoExists): string
    {
        $table      = $this->toSnakePlural($name);
        $daoUse     = $daoExists ? "\nuse App\\Dao\\{$name}Dao;" : '';
        $daoParam   = $daoExists ? "\n        private {$name}Dao \${$entity}Dao," : '';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Controllers;
        {$daoUse}
        use Controller\AbstractController;
        use Core\Exception\NotFoundException;
        use Core\Http\Response;
        use Core\Request;
        use Core\View;

        final class {$name}Controller extends AbstractController
        {
            public function __construct(
                View            \$view,
                private Request \$request,{$daoParam}
            ) {
                parent::__construct(\$view);
            }

            /** GET /{$table} */
            public function index(): Response
            {
                \$items = \$this->{$entity}Dao->findAll();
                return \$this->json(['data' => \$items]);
            }

            /** GET /{$table}/:id */
            public function show(string \$id): Response
            {
                \$item = \$this->{$entity}Dao->findById((int) \$id)
                    ?? throw new NotFoundException("{$name} #{" . "\$id} introuvable.");

                return \$this->json(['data' => \$item]);
            }

            /** POST /{$table} */
            public function store(): Response
            {
                \$data = \$this->request->body ?? \$this->request->post();

                \$v = \$this->validate(\$data, [
                    // TODO : définir vos règles de validation
                ]);

                if (\$v->fails()) {
                    return \$this->json(['errors' => \$v->errors()], 422);
                }

                \$id = \$this->{$entity}Dao->insert([
                    // TODO : mapper \$data vers les colonnes
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                return \$this->json(['data' => ['id' => \$id]], 201);
            }

            /** PUT /{$table}/:id */
            public function update(string \$id): Response
            {
                \$data = \$this->request->body ?? \$this->request->post();

                \$v = \$this->validate(\$data, [
                    // TODO : définir vos règles de validation
                ]);

                if (\$v->fails()) {
                    return \$this->json(['errors' => \$v->errors()], 422);
                }

                \$this->{$entity}Dao->update((int) \$id, [
                    // TODO : mapper \$data vers les colonnes
                ]);

                return \$this->json(['data' => ['id' => \$id]]);
            }

            /** DELETE /{$table}/:id */
            public function destroy(string \$id): Response
            {
                \$deleted = \$this->{$entity}Dao->delete((int) \$id);

                if (\$deleted === 0) {
                    throw new NotFoundException("{$name} #{" . "\$id} introuvable.");
                }

                return \$this->json(['message' => '{$name} supprimé.']);
            }
        }
        PHP;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    private function toSnakePlural(string $name): string
    {
        $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        }
        if (str_ends_with($snake, 's') || str_ends_with($snake, 'x')) {
            return $snake . 'es';
        }
        return $snake . 's';
    }

    /** Convertit PascalCase en chemin de vue snake_case. Ex: BlogPost → blog_post */
    private function toViewPath(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }
}
