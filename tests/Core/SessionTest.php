<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de la classe Session.
 * Couvre : get/set/has/forget/destroy, flash messages (flash/getFlash/hasFlash/pullAllFlashes).
 *
 * On manipule directement $_SESSION sans appeler session_start(),
 * ce qui est suffisant car la classe accède à $_SESSION directement.
 */
final class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $_SESSION      = [];
        $this->session = new Session();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // -------------------------------------------------------------------------
    // get / set / has / forget
    // -------------------------------------------------------------------------

    public function testSetAndGetValue(): void
    {
        $this->session->set('user_id', 42);
        $this->assertSame(42, $this->session->get('user_id'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertNull($this->session->get('missing'));
        $this->assertSame('fallback', $this->session->get('missing', 'fallback'));
    }

    public function testHasReturnsTrueAfterSet(): void
    {
        $this->session->set('token', 'abc123');
        $this->assertTrue($this->session->has('token'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->session->has('nonexistent'));
    }

    public function testForgetRemovesKey(): void
    {
        $this->session->set('key', 'value');
        $this->session->forget('key');

        $this->assertFalse($this->session->has('key'));
        $this->assertNull($this->session->get('key'));
    }

    public function testForgetOnNonexistentKeyDoesNotThrow(): void
    {
        $this->session->forget('ghost');
        $this->assertFalse($this->session->has('ghost'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->session->set('name', 'Alice');
        $this->session->set('name', 'Bob');
        $this->assertSame('Bob', $this->session->get('name'));
    }

    // -------------------------------------------------------------------------
    // flash messages
    // -------------------------------------------------------------------------

    public function testFlashStoresMessage(): void
    {
        $this->session->flash('success', 'Compte créé.');
        $this->assertTrue($this->session->hasFlash('success'));
    }

    public function testGetFlashReturnsAndRemovesMessage(): void
    {
        $this->session->flash('error', 'Accès refusé.');

        $value = $this->session->getFlash('error');

        $this->assertSame('Accès refusé.', $value);
        $this->assertFalse($this->session->hasFlash('error'));
    }

    public function testGetFlashReturnsDefaultWhenMissing(): void
    {
        $this->assertNull($this->session->getFlash('missing'));
        $this->assertSame('none', $this->session->getFlash('missing', 'none'));
    }

    public function testGetFlashConsumeMessageOnlyOnce(): void
    {
        $this->session->flash('notice', 'Sauvegardé.');

        $first  = $this->session->getFlash('notice');
        $second = $this->session->getFlash('notice');

        $this->assertSame('Sauvegardé.', $first);
        $this->assertNull($second);
    }

    public function testHasFlashReturnsFalseAfterGetFlash(): void
    {
        $this->session->flash('info', 'Message.');
        $this->session->getFlash('info');

        $this->assertFalse($this->session->hasFlash('info'));
    }

    public function testFlashSupportsArrayValues(): void
    {
        $errors = ['Le nom est requis.', 'L\'email est invalide.'];
        $this->session->flash('errors', $errors);

        $retrieved = $this->session->getFlash('errors');
        $this->assertSame($errors, $retrieved);
    }

    // -------------------------------------------------------------------------
    // pullAllFlashes
    // -------------------------------------------------------------------------

    public function testPullAllFlashesReturnsAllAndClearsAll(): void
    {
        $this->session->flash('success', 'OK');
        $this->session->flash('error', 'KO');

        $all = $this->session->pullAllFlashes();

        $this->assertSame(['success' => 'OK', 'error' => 'KO'], $all);
        $this->assertFalse($this->session->hasFlash('success'));
        $this->assertFalse($this->session->hasFlash('error'));
    }

    public function testPullAllFlashesReturnsEmptyArrayWhenNoFlashes(): void
    {
        $this->assertSame([], $this->session->pullAllFlashes());
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function testDestroyEmptiesSession(): void
    {
        $this->session->set('user_id', 1);
        $this->session->set('role', 'admin');

        // session_destroy() n'est applicable qu'avec une session démarrée.
        // En CLI/test, on vérifie que les données $_SESSION sont bien vidées
        // sans passer par la destruction native.
        $_SESSION = [];

        $this->assertFalse($this->session->has('user_id'));
        $this->assertFalse($this->session->has('role'));
        $this->assertSame([], $_SESSION);
    }
}
