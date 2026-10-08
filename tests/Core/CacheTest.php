<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du cache fichier.
 * Couvre : get/set/has/forget/flush/remember + expiration TTL.
 * Utilise un répertoire temporaire isolé, nettoyé après chaque test.
 */
final class CacheTest extends TestCase
{
    private string $cacheDir;
    private Cache  $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'astral_cache_test_' . uniqid();
        $this->cache    = new Cache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        // Supprime tous les fichiers .cache du répertoire temporaire
        if (is_dir($this->cacheDir)) {
            foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->cacheDir);
        }
    }

    // -------------------------------------------------------------------------
    // set / get
    // -------------------------------------------------------------------------

    public function testSetAndGetString(): void
    {
        $this->cache->set('greeting', 'Hello', 60);
        $this->assertSame('Hello', $this->cache->get('greeting'));
    }

    public function testSetAndGetArray(): void
    {
        $data = ['id' => 1, 'name' => 'Alice'];
        $this->cache->set('user', $data, 60);
        $this->assertSame($data, $this->cache->get('user'));
    }

    public function testSetAndGetInteger(): void
    {
        $this->cache->set('count', 42, 60);
        $this->assertSame(42, $this->cache->get('count'));
    }

    public function testGetReturnsNullWhenKeyMissing(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame('fallback', $this->cache->get('nonexistent', 'fallback'));
    }

    public function testGetReturnsNullAfterTtlExpired(): void
    {
        $this->cache->set('ephemeral', 'gone', -1); // TTL expiré dans le passé
        $this->assertNull($this->cache->get('ephemeral'));
    }

    // -------------------------------------------------------------------------
    // has
    // -------------------------------------------------------------------------

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set('key', 'value', 60);
        $this->assertTrue($this->cache->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->has('missing'));
    }

    public function testHasReturnsFalseAfterExpiry(): void
    {
        $this->cache->set('temp', 'value', -1);
        $this->assertFalse($this->cache->has('temp'));
    }

    // -------------------------------------------------------------------------
    // forget
    // -------------------------------------------------------------------------

    public function testForgetRemovesEntry(): void
    {
        $this->cache->set('deleteme', 'yes', 60);
        $this->cache->forget('deleteme');

        $this->assertFalse($this->cache->has('deleteme'));
        $this->assertNull($this->cache->get('deleteme'));
    }

    public function testForgetOnNonexistentKeyDoesNotThrow(): void
    {
        $this->cache->forget('ghost');
        $this->assertFalse($this->cache->has('ghost'));
    }

    // -------------------------------------------------------------------------
    // flush
    // -------------------------------------------------------------------------

    public function testFlushRemovesAllEntries(): void
    {
        $this->cache->set('a', 'alpha', 60);
        $this->cache->set('b', 'beta', 60);
        $this->cache->set('c', 'gamma', 60);

        $count = $this->cache->flush();

        $this->assertSame(3, $count);
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
        $this->assertFalse($this->cache->has('c'));
    }

    public function testFlushReturnsZeroWhenCacheEmpty(): void
    {
        $this->assertSame(0, $this->cache->flush());
    }

    public function testFlushReturnsZeroWhenDirectoryDoesNotExist(): void
    {
        $emptyCache = new Cache('/nonexistent/path/astral_test');
        $this->assertSame(0, $emptyCache->flush());
    }

    // -------------------------------------------------------------------------
    // remember
    // -------------------------------------------------------------------------

    public function testRememberExecutesCallbackOnFirstCall(): void
    {
        $calls = 0;

        $result = $this->cache->remember('expensive', 60, function () use (&$calls) {
            $calls++;
            return 'computed';
        });

        $this->assertSame('computed', $result);
        $this->assertSame(1, $calls);
    }

    public function testRememberReturnsCachedValueOnSecondCall(): void
    {
        $calls = 0;

        $this->cache->remember('expensive', 60, function () use (&$calls) {
            $calls++;
            return 'computed';
        });

        $this->cache->remember('expensive', 60, function () use (&$calls) {
            $calls++;
            return 'computed';
        });

        $this->assertSame(1, $calls);
    }

    public function testRememberRecomputesAfterExpiry(): void
    {
        $calls = 0;

        $this->cache->remember('short', -1, function () use (&$calls) {
            $calls++;
            return 'v1';
        });

        $result = $this->cache->remember('short', 60, function () use (&$calls) {
            $calls++;
            return 'v2';
        });

        $this->assertSame('v2', $result);
        $this->assertSame(2, $calls);
    }
}
