<?php

declare(strict_types=1);

namespace Database\Migration;

use PDO;

/**
 * Classe abstraite de base pour toutes les migrations de schéma.
 *
 * Chaque migration est un fichier dans database/migrations/ dont le nom
 * suit la convention : YYYY_MM_DD_NNNNNN_nom_en_snake_case.php
 * La classe définie dans ce fichier doit être le StudlyCase du suffixe.
 *
 * Exemple :
 *   Fichier : 2026_03_11_000001_create_users_table.php
 *   Classe  : CreateUsersTable
 */
abstract class Migration
{
    /**
     * Applique la migration (CREATE TABLE, ALTER TABLE, INSERT…).
     */
    abstract public function up(PDO $pdo): void;

    /**
     * Annule la migration (DROP TABLE, DROP COLUMN, DELETE…).
     */
    abstract public function down(PDO $pdo): void;
}
