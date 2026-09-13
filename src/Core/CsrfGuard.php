<?php

declare(strict_types=1);

namespace Core;

use Core\Exception\CsrfException;

/**
 * Protection CSRF par token de session.
 *
 * Génère un token aléatoire par session, l'expose via field() pour
 * l'inclure dans les formulaires, et le vérifie sur toute requête
 * à effet de bord (POST, PUT, PATCH, DELETE).
 *
 * Usage dans une vue :
 *   <form method="POST">
 *       <?= $csrf->field() ?>
 *       ...
 *   </form>
 */
final class CsrfGuard
{
    private const SESSION_KEY = '_csrf_token';
    private const TOKEN_BYTES = 32;

    public function __construct(private Session $session) {}

    // -------------------------------------------------------------------------
    // Génération du token
    // -------------------------------------------------------------------------

    /** Retourne le token existant ou en génère un nouveau. */
    public function token(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(self::TOKEN_BYTES)));
        }

        return (string) $this->session->get(self::SESSION_KEY);
    }

    /**
     * Retourne un champ HTML hidden prêt à coller dans un <form>.
     *
     * <input type="hidden" name="_token" value="…">
     */
    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_token" value="%s">',
            htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8'),
        );
    }

    // -------------------------------------------------------------------------
    // Vérification
    // -------------------------------------------------------------------------

    /** Vérifie un token soumis sans lever d'exception. */
    public function verify(string $submitted): bool
    {
        $stored = (string) $this->session->get(self::SESSION_KEY, '');
        return $stored !== '' && hash_equals($stored, $submitted);
    }

    /**
     * Vérifie le token de la requête et lève CsrfException si invalide.
     * N'agit que sur les méthodes à effet de bord.
     *
     * @throws CsrfException
     */
    public function verifyRequest(Request $request): void
    {
        $stateful = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (!in_array($request->method, $stateful, true)) {
            return;
        }

        $submitted = (string) ($request->input('_token') ?? $request->header('X-CSRF-Token') ?? '');

        if (!$this->verify($submitted)) {
            throw new CsrfException('Token CSRF invalide ou manquant.');
        }
    }
}
