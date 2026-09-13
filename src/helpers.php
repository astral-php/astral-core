<?php

declare(strict_types=1);

use Core\Dumper\Dumper;

/**
 * Affiche une représentation lisible d'une ou plusieurs variables.
 * Fonctionne en HTML (rendu coloré) et en CLI.
 *
 * Usage : dump($user, $request->body, $array);
 */
if (!function_exists('dump')) {
    function dump(mixed ...$vars): void
    {
        Dumper::dump(...$vars);
    }
}

/**
 * Comme dump(), mais stoppe l'exécution immédiatement après.
 *
 * Usage : dd($user);
 */
if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        Dumper::dd(...$vars);
    }
}
