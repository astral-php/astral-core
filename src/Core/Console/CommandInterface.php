<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Contrat d'une commande CLI.
 *
 * Chaque commande implémente cette interface et est enregistrée
 * dans bin/console via Console::register().
 *
 * Codes de retour conventionnels :
 *   0  — succès
 *   1  — erreur générique
 */
interface CommandInterface
{
    /** Nom utilisé en ligne de commande (ex: cache:clear). */
    public function getName(): string;

    /** Description affichée dans la liste des commandes. */
    public function getDescription(): string;

    /**
     * Exécute la commande.
     *
     * @param  list<string> $args Arguments positionnels passés après le nom
     * @return int Code de sortie (0 = succès)
     */
    public function execute(array $args, Console $console): int;
}
