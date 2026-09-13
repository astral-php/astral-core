<?php

declare(strict_types=1);

namespace Database;

use Database\Exception\DatabaseException;
use PDO;
use PDOStatement;
use PDOException;

/**
 * DAO (Data Access Object) abstrait.
 *
 * Fournit les opérations CRUD génériques via PDO.
 * Les DAOs concrets héritent de cette classe et définissent
 * la table cible ($table) ainsi que la classe de modèle ($modelClass).
 *
 * @template T of object
 */
abstract class AbstractDao
{
    protected PDO $pdo;

    abstract protected function getTable(): string;

    /**
     * FQCN du modèle à hydrater.
     * Si null, retourne des tableaux associatifs.
     */
    protected function getModelClass(): ?string
    {
        return null;
    }

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // -------------------------------------------------------------------------
    // CRUD générique
    // -------------------------------------------------------------------------

    /**
     * Retourne tous les enregistrements.
     *
     * @return list<T|array<string,mixed>>
     */
    public function findAll(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $sql       = "SELECT * FROM {$this->getTable()} ORDER BY {$orderBy} {$direction}";
        return $this->query($sql);
    }

    /**
     * Retourne un enregistrement par son identifiant, ou null.
     *
     * @return T|array<string,mixed>|null
     */
    public function findById(int $id): object|array|null
    {
        $rows = $this->query(
            "SELECT * FROM {$this->getTable()} WHERE id = :id LIMIT 1",
            [':id' => $id],
        );
        return $rows[0] ?? null;
    }

    /**
     * Recherche selon des critères simples (colonne = valeur, liés par AND).
     *
     * @param  array<string, mixed>         $criteria
     * @return list<T|array<string,mixed>>
     */
    public function findBy(array $criteria): array
    {
        $where  = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $placeholder    = ":{$column}";
            $where[]        = "{$column} = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "SELECT * FROM {$this->getTable()} WHERE " . implode(' AND ', $where);
        return $this->query($sql, $params);
    }

    /**
     * Insère un enregistrement et retourne l'ID généré.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->getTable(),
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $params = [];
        foreach ($data as $column => $value) {
            $params[":{$column}"] = $value;
        }

        $this->execute($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met à jour un enregistrement par son ID.
     *
     * @param  array<string, mixed> $data
     */
    public function update(int $id, array $data): int
    {
        $setParts = array_map(fn($c) => "{$c} = :{$c}", array_keys($data));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = :id',
            $this->getTable(),
            implode(', ', $setParts),
        );

        $params = [];
        foreach ($data as $column => $value) {
            $params[":{$column}"] = $value;
        }
        $params[':id'] = $id;

        return $this->execute($sql, $params);
    }

    /**
     * Supprime un enregistrement par son ID.
     */
    public function delete(int $id): int
    {
        return $this->execute(
            "DELETE FROM {$this->getTable()} WHERE id = :id",
            [':id' => $id],
        );
    }

    // -------------------------------------------------------------------------
    // Pagination & comptage
    // -------------------------------------------------------------------------

    /**
     * Retourne une page de résultats et les méta-données de pagination.
     *
     * @return array{
     *     data:     list<T|array<string,mixed>>,
     *     total:    int,
     *     pages:    int,
     *     current:  int,
     *     per_page: int
     * }
     */
    public function paginate(
        int    $page      = 1,
        int    $perPage   = 15,
        string $orderBy   = 'id',
        string $direction = 'ASC',
    ): array {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $page      = max(1, $page);
        $offset    = ($page - 1) * $perPage;

        $total = $this->count();

        $data = $this->query(
            "SELECT * FROM {$this->getTable()} ORDER BY {$orderBy} {$direction} LIMIT {$perPage} OFFSET {$offset}",
        );

        return [
            'data'     => $data,
            'total'    => $total,
            'pages'    => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'current'  => $page,
            'per_page' => $perPage,
        ];
    }

    /** Retourne le nombre total d'enregistrements de la table. */
    public function count(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM {$this->getTable()}")
            ->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Helpers de relations — ORM léger
    // -------------------------------------------------------------------------

    /**
     * Charge une liste d'entités liées par clé étrangère (relation 1→N).
     *
     * Usage dans un DAO concret :
     *   $articles = $this->hasMany(Article::class, 'articles', 'category_id', $categoryId);
     *
     * @template R of object
     * @param  class-string<R> $relatedClass  Classe du modèle à hydrater
     * @param  string          $table         Nom de la table distante
     * @param  string          $foreignKey    Colonne de clé étrangère dans la table distante
     * @param  int             $localId       Valeur de la clé locale (généralement l'id du parent)
     * @param  string          $orderBy       Colonne de tri (défaut: id)
     * @param  string          $direction     ASC ou DESC
     * @return list<R>
     */
    protected function hasMany(
        string $relatedClass,
        string $table,
        string $foreignKey,
        int    $localId,
        string $orderBy   = 'id',
        string $direction = 'ASC',
    ): array {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$table} WHERE {$foreignKey} = :id ORDER BY {$orderBy} {$direction}"
            );
            $stmt->execute([':id' => $localId]);
            $stmt->setFetchMode(PDO::FETCH_CLASS, $relatedClass);

            /** @var list<R> */
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException($e->getMessage(), previous: $e);
        }
    }

    /**
     * Charge l'entité parente par clé étrangère (relation N→1).
     *
     * Usage dans un DAO concret :
     *   $category = $this->belongsTo(Category::class, 'categories', $article->category_id);
     *
     * @template R of object
     * @param  class-string<R> $relatedClass  Classe du modèle à hydrater
     * @param  string          $table         Nom de la table parente
     * @param  int             $foreignId     Valeur de la clé étrangère portée par l'enfant
     * @return R|null          null si $foreignId = 0 ou enregistrement introuvable
     */
    protected function belongsTo(string $relatedClass, string $table, int $foreignId): ?object
    {
        if ($foreignId <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $foreignId]);
            $stmt->setFetchMode(PDO::FETCH_CLASS, $relatedClass);
            $result = $stmt->fetch();

            /** @var R|null */
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            throw new DatabaseException($e->getMessage(), previous: $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers bas-niveau
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>         $params
     * @return list<T|array<string,mixed>>
     */
    protected function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->prepare($sql, $params);

            if ($this->getModelClass() !== null) {
                $stmt->setFetchMode(PDO::FETCH_CLASS, $this->getModelClass());
            }

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->prepare($sql, $params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new DatabaseException($e->getMessage(), previous: $e);
        }
    }

    /** @param array<string, mixed> $params */
    private function prepare(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
