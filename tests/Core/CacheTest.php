<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Tests unitaires du cache fichier 2.0 (spec C1–C8).
 */
final class CacheTest extends TestCase
{
    private string $cacheDir;
    private Cache  $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'astral_cache_test_' . uniqid('', true);
        $this->cache    = new Cache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->cacheDir);
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

    public function testGetAndHasWithStoredNull(): void
    {
        $this->cache->set('nullable', null, 60);

        $this->assertTrue($this->cache->has('nullable'));
        $this->assertNull($this->cache->get('nullable'));
        $this->assertSame('miss', $this->cache->get('other', 'miss'));
    }

    public function testGetReturnsDefaultAfterExpiry(): void
    {
        $this->writeExpiredPayload('ephemeral', 'gone');

        $this->assertNull($this->cache->get('ephemeral'));
        $this->assertFalse($this->cache->has('ephemeral'));
    }

    public function testCorruptPayloadIsTreatedAsMiss(): void
    {
        $this->ensureDir();
        file_put_contents($this->filePath('bad'), 'not-serialized');

        $this->assertNull($this->cache->get('bad'));
        $this->assertFalse($this->cache->has('bad'));
    }

    public function testObjectInPayloadIsRejectedOnRead(): void
    {
        $this->ensureDir();
        $payload = serialize([
            'expires_at' => time() + 60,
            'value'      => new stdClass(),
        ]);
        file_put_contents($this->filePath('obj'), $payload);

        $this->assertNull($this->cache->get('obj'));
        $this->assertFalse($this->cache->has('obj'));
    }

    // -------------------------------------------------------------------------
    // TTL
    // -------------------------------------------------------------------------

    public function testSetRejectsZeroTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set('k', 'v', 0);
    }

    public function testSetRejectsNegativeTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set('k', 'v', -1);
    }

    public function testRememberRejectsInvalidTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->remember('k', 0, static fn () => 'x');
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
        $this->writeExpiredPayload('temp', 'value');
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
        $emptyCache = new Cache(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'astral_cache_missing_' . uniqid('', true));
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

    public function testRememberKeepsCachedNull(): void
    {
        $calls = 0;

        $this->cache->remember('maybe', 60, function () use (&$calls) {
            $calls++;
            return null;
        });

        $result = $this->cache->remember('maybe', 60, function () use (&$calls) {
            $calls++;
            return 'should-not-run';
        });

        $this->assertNull($result);
        $this->assertSame(1, $calls);
    }

    public function testRememberRecomputesAfterExpiry(): void
    {
        $calls = 0;

        $this->writeExpiredPayload('short', 'v1');

        $result = $this->cache->remember('short', 60, function () use (&$calls) {
            $calls++;
            return 'v2';
        });

        $this->assertSame('v2', $result);
        $this->assertSame(1, $calls);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function ensureDir(): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException('Unable to create test cache dir.');
        }
    }

    private function filePath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    private function writeExpiredPayload(string $key, mixed $value): void
    {
        $this->ensureDir();
        $payload = serialize([
            'expires_at' => time() - 10,
            'value'      => $value,
        ]);
        file_put_contents($this->filePath($key), $payload);
    }
}
