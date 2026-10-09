<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\FilesystemCache;
use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\PHPUnit\AmtgardTestCase;

class FilesystemCacheTest extends AmtgardTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/aaro-c05-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->directory);
        parent::tearDown();
    }

    public function testBuild_withoutDirectory_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filesystem cache directory is required');

        FilesystemCache::builder()->build();
    }

    public function testBuild_withEmptyDirectory_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filesystem cache directory is required');

        FilesystemCache::builder()->directory('')->build();
    }

    public function testGet_withExistingKey_returnsValue(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));
        $cache->set('test_key', 'test_value');

        self::assertSame('test_value', $cache->get('test_key'));
    }

    public function testGet_withNonExistingKey_returnsDefault(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));

        self::assertSame('default_value', $cache->get('missing', 'default_value'));
        self::assertDirectoryDoesNotExist($this->entryDirectory());
    }

    public function testSet_withNewKey_storesHashedFile(): void
    {
        $clock = new FakeClock(5_000);
        $cache = $this->cacheWithClock($clock);
        $key = 'amtgard_orm:ork:schema:users';

        self::assertTrue($cache->set($key, 'document', 30));
        self::assertTrue($cache->has($key));
        self::assertSame('document', $cache->get($key));

        $path = $this->entryPath($key);
        self::assertFileExists($path);
        self::assertSame(hash('sha256', $key), basename($path));
        self::assertSame(
            ['value' => 'document', 'expiresAt' => 5_030],
            json_decode((string) file_get_contents($path), true)
        );
        self::assertFileDoesNotExist($this->entryDirectory() . DIRECTORY_SEPARATOR . 'users');
    }

    public function testSet_withExistingKey_replacesValue(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));
        $cache->set('existing_key', 'initial_value');

        self::assertTrue($cache->set('existing_key', 'new_value'));
        self::assertSame('new_value', $cache->get('existing_key'));
        self::assertCount(1, $this->ownedFiles());
    }

    public function testSet_isVisibleToAnotherAdapterOnTheSameDirectory(): void
    {
        $clock = new FakeClock(100);
        $first = $this->cacheWithClock($clock);
        $second = $this->cacheWithClock($clock);
        $first->set('shared', 'value', null);

        self::assertSame('value', $second->get('shared'));
        self::assertTrue($second->has('shared'));
    }

    public function testSet_withKeyThatLooksLikeAPath_staysInsideTheEntryDirectory(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));
        $key = '../outside/../../etc/passwd';

        self::assertTrue($cache->set($key, 'secret'));

        $path = $this->entryPath($key);
        $realEntry = realpath($this->entryDirectory());
        $realFile = realpath($path);
        self::assertNotFalse($realEntry);
        self::assertNotFalse($realFile);
        self::assertTrue(str_starts_with($realFile, $realEntry . DIRECTORY_SEPARATOR));
        self::assertSame(hash('sha256', $key), basename($realFile));
        self::assertFileDoesNotExist(dirname($this->directory) . '/passwd');
        self::assertFileDoesNotExist($this->directory . '/outside');
        self::assertDirectoryDoesNotExist($this->entryDirectory() . '/outside');
    }

    public function testSet_createsANestedDirectoryWhenMissing(): void
    {
        $nested = $this->directory . '/level/cache';
        $cache = FilesystemCache::builder()
            ->directory($nested)
            ->clock(new FakeClock(10))
            ->build();

        self::assertTrue($cache->set('nested', 'value'));
        self::assertDirectoryExists($nested . DIRECTORY_SEPARATOR . 'aaro');
        self::assertSame('value', $cache->get('nested'));
    }

    public function testSet_withNullTtl_doesNotExpire(): void
    {
        $clock = new FakeClock(1_000);
        $cache = $this->cacheWithClock($clock);

        self::assertTrue($cache->set('durable', 'value', null));
        $clock->advance(100_000);

        self::assertTrue($cache->has('durable'));
        self::assertSame('value', $cache->get('durable'));
        self::assertNull($this->storedExpiry('durable'));
    }

    public function testSet_withZeroTtl_deletesFile(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));
        $cache->set('gone', 'value');

        self::assertTrue($cache->set('gone', 'replacement', 0));
        self::assertFileDoesNotExist($this->entryPath('gone'));
        self::assertFalse($cache->has('gone'));
        self::assertSame('default', $cache->get('gone', 'default'));
    }

    public function testSet_withNegativeTtl_deletesFile(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));
        $cache->set('gone', 'value');

        self::assertTrue($cache->set('gone', 'replacement', -1));
        self::assertFileDoesNotExist($this->entryPath('gone'));
        self::assertFalse($cache->has('gone'));
        self::assertNull($cache->get('gone'));
    }

    public function testSet_withNegativeTtlOnMissingKey_leavesKeyAbsent(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));

        self::assertTrue($cache->set('missing', 'value', -5));
        self::assertFileDoesNotExist($this->entryPath('missing'));
        self::assertDirectoryDoesNotExist($this->entryDirectory());
        self::assertFalse($cache->has('missing'));
    }

    public function testSet_withPositiveIntTtl_expiresAgainstClock(): void
    {
        $clock = new FakeClock(5_000);
        $cache = $this->cacheWithClock($clock);

        self::assertTrue($cache->set('temp', 'value', 10));
        self::assertSame(5_010, $this->storedExpiry('temp'));
        self::assertTrue($cache->has('temp'));

        $clock->advance(9);
        self::assertTrue($cache->has('temp'));
        self::assertSame('value', $cache->get('temp', 'default'));

        $clock->advance(1);
        self::assertFalse($cache->has('temp'));
        self::assertFileDoesNotExist($this->entryPath('temp'));
        self::assertSame('default', $cache->get('temp', 'default'));
    }

    public function testSet_withDateIntervalTtl_expiresAgainstClock(): void
    {
        $clock = new FakeClock(8_000);
        $cache = $this->cacheWithClock($clock);
        $ttl = new \DateInterval('PT1H30M');

        self::assertTrue($cache->set('interval', 'value', $ttl));
        self::assertTrue($cache->set('again', 'other', $ttl));
        self::assertSame(8_000 + 5400, $this->storedExpiry('interval'));

        $clock->advance(5399);
        self::assertTrue($cache->has('interval'));
        self::assertSame('other', $cache->get('again'));

        $clock->advance(1);
        self::assertFalse($cache->has('interval'));
        self::assertSame('default', $cache->get('again', 'default'));
        self::assertFileDoesNotExist($this->entryPath('again'));
    }

    public function testSet_withZeroDateInterval_deletesFile(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(8_000));
        $cache->set('zero', 'value');

        self::assertTrue($cache->set('zero', 'replacement', new \DateInterval('PT0S')));
        self::assertFileDoesNotExist($this->entryPath('zero'));
        self::assertFalse($cache->has('zero'));
        self::assertSame('default', $cache->get('zero', 'default'));
    }

    public function testSet_withNegativeDateInterval_deletesFile(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(8_000));
        $cache->set('negative', 'value');
        $ttl = new \DateInterval('PT5S');
        $ttl->invert = 1;

        self::assertTrue($cache->set('negative', 'replacement', $ttl));
        self::assertFileDoesNotExist($this->entryPath('negative'));
        self::assertFalse($cache->has('negative'));
    }

    public function testSet_withDayDateInterval_remainsUntilThatDayPasses(): void
    {
        $clock = new FakeClock(10_000);
        $cache = $this->cacheWithClock($clock);

        self::assertTrue($cache->set('day', 'value', new \DateInterval('P1D')));
        $clock->advance(86399);
        self::assertTrue($cache->has('day'));

        $clock->advance(1);
        self::assertFalse($cache->has('day'));
        self::assertFileDoesNotExist($this->entryPath('day'));
    }

    public function testGet_withExpiredKey_returnsDefault(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cacheWithClock($clock);
        $cache->set('expired', 'value', 1);
        $clock->advance(1);

        self::assertSame('default', $cache->get('expired', 'default'));
        self::assertFileDoesNotExist($this->entryPath('expired'));
        self::assertFalse($cache->has('expired'));
    }

    public function testSet_afterExpiry_storesAFreshValue(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cacheWithClock($clock);
        $cache->set('refill', 'old', 1);
        $clock->advance(1);
        $cache->set('refill', 'new', null);

        self::assertSame('new', $cache->get('refill', 'default'));
        self::assertNull($this->storedExpiry('refill'));
    }

    public function testSet_replacesTtlOfExistingKey(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cacheWithClock($clock);
        $cache->set('replace', 'value', 5);
        $cache->set('replace', 'later', null);
        $clock->advance(50);

        self::assertSame('later', $cache->get('replace'));
    }

    public function testSet_storesNullFalseZeroAndArray(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));

        $cache->set('null', null);
        $cache->set('false', false);
        $cache->set('zero', 0);
        $cache->set('empty', '');
        $cache->set('list', ['a' => 1, 'b' => [true, null]]);

        self::assertTrue($cache->has('null'));
        self::assertNull($cache->get('null', 'default'));
        self::assertTrue($cache->has('false'));
        self::assertFalse($cache->get('false', 'default'));
        self::assertSame(0, $cache->get('zero', 1));
        self::assertSame('', $cache->get('empty', 'default'));
        self::assertSame(['a' => 1, 'b' => [true, null]], $cache->get('list'));
    }

    public function testSet_withPositiveTtl_usesSystemClockWhenNoneIsInjected(): void
    {
        $cache = FilesystemCache::builder()->directory($this->directory)->build();

        self::assertTrue($cache->set('system', 'value', 3600));
        self::assertTrue($cache->has('system'));
        self::assertSame('value', $cache->get('system'));
        self::assertTrue($cache->set('day', 'value', new \DateInterval('PT1H')));
        self::assertTrue($cache->has('day'));
        self::assertGreaterThan(time(), $this->storedExpiry('system'));
    }

    public function testGet_withCorruptFile_returnsDefault(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));
        $cache->set('good', 'value');
        $this->plant('good', '{');
        $this->plant('empty', '');
        $this->plant('scalar', 'null');
        $this->plant('text', '"hello"');
        $this->plant('missing-value', '{"expiresAt":null}');
        $this->plant('missing-expiry', '{"value":"x"}');
        $this->plant('bad-expiry', '{"value":"x","expiresAt":"soon"}');
        $this->plant('float-expiry', '{"value":"x","expiresAt":1.5}');

        self::assertFalse($cache->has('good'));
        self::assertSame('default', $cache->get('good', 'default'));
        self::assertFileExists($this->entryPath('good'));
        self::assertFalse($cache->has('empty'));
        self::assertSame('default', $cache->get('empty', 'default'));
        self::assertFalse($cache->has('scalar'));
        self::assertFalse($cache->has('text'));
        self::assertFalse($cache->has('missing-value'));
        self::assertSame('default', $cache->get('missing-value', 'default'));
        self::assertFalse($cache->has('missing-expiry'));
        self::assertSame('default', $cache->get('missing-expiry', 'default'));
        self::assertFalse($cache->has('bad-expiry'));
        self::assertFalse($cache->has('float-expiry'));
        self::assertSame('default', $cache->get('float-expiry', 'default'));
    }

    public function testDelete_withExistingKey_removesFile(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));
        $cache->set('delete_key', 'delete_value');

        self::assertTrue($cache->delete('delete_key'));
        self::assertFileDoesNotExist($this->entryPath('delete_key'));
        self::assertFalse($cache->has('delete_key'));
    }

    public function testDelete_withNonExistingKey_returnsTrue(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));

        self::assertTrue($cache->delete('missing'));
        self::assertFalse(is_file($this->entryPath('missing')));
    }

    public function testDeleteHasAndClear_whenDirectoryIsMissing_doNotCreateIt(): void
    {
        $cache = FilesystemCache::builder()
            ->directory($this->directory . '/absent')
            ->build();

        self::assertTrue($cache->delete('anything'));
        self::assertFalse($cache->has('anything'));
        self::assertSame('default', $cache->get('anything', 'default'));
        self::assertTrue($cache->clear());
        self::assertDirectoryDoesNotExist($this->directory . '/absent');
    }

    public function testClear_removesOnlyOwnedFilesInTheEntryDirectory(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));
        mkdir($this->directory, 0777, true);
        $keep = $this->directory . '/keep.txt';
        $parentHash = $this->directory . '/' . str_repeat('ab', 32);
        file_put_contents($keep, 'keep');
        file_put_contents($parentHash, 'parent');

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $entry = $this->entryDirectory();
        $notes = $entry . '/notes.txt';
        $short = $entry . '/' . str_repeat('a', 63);
        $nonHex = $entry . '/' . str_repeat('g', 64);
        $owned = $entry . '/' . str_repeat('0', 64);
        $ownedDir = $entry . '/' . str_repeat('f', 64);
        file_put_contents($notes, 'notes');
        file_put_contents($short, 'short');
        file_put_contents($nonHex, 'nonhex');
        file_put_contents($owned, 'owned');
        mkdir($ownedDir);

        self::assertTrue($cache->clear());

        self::assertFalse($cache->has('key1'));
        self::assertFalse($cache->has('key2'));
        self::assertFileDoesNotExist($this->entryPath('key1'));
        self::assertFileDoesNotExist($owned);
        self::assertFileExists($keep);
        self::assertFileExists($parentHash);
        self::assertFileExists($notes);
        self::assertFileExists($short);
        self::assertFileExists($nonHex);
        self::assertDirectoryExists($ownedDir);
        self::assertDirectoryExists($entry);
        self::assertSame('keep', (string) file_get_contents($keep));
    }

    public function testClear_onEmptyEntryDirectory_returnsTrue(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));
        mkdir($this->entryDirectory(), 0777, true);

        self::assertTrue($cache->clear());
        self::assertDirectoryExists($this->entryDirectory());
    }

    public function testGetMultiple_throwsNotImplementedException(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));

        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('getMultiple is not implemented yet.');

        $cache->getMultiple(['key1', 'key2']);
    }

    public function testSetMultiple_throwsNotImplementedException(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));

        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('setMultiple is not implemented yet.');

        $cache->setMultiple(['key1' => 'value1']);
    }

    public function testDeleteMultiple_throwsNotImplementedException(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));

        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('deleteMultiple is not implemented yet.');

        $cache->deleteMultiple(['key1']);
    }

    public function testHas_withExistingKey_returnsTrue(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));
        $cache->set('has_key', 'has_value');

        self::assertTrue($cache->has('has_key'));
    }

    public function testHas_withNonExistingKey_returnsFalse(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));

        self::assertFalse($cache->has('missing'));
    }

    private function cacheWithClock(FakeClock $clock): FilesystemCache
    {
        return FilesystemCache::builder()
            ->directory($this->directory)
            ->clock($clock)
            ->build();
    }

    private function entryDirectory(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'aaro';
    }

    private function entryPath(string $key): string
    {
        return $this->entryDirectory() . DIRECTORY_SEPARATOR . hash('sha256', $key);
    }

    private function storedExpiry(string $key): mixed
    {
        $decoded = json_decode((string) file_get_contents($this->entryPath($key)), true);
        return $decoded['expiresAt'];
    }

    private function plant(string $key, string $contents): void
    {
        $path = $this->entryPath($key);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $contents);
    }

    /**
     * @return list<string>
     */
    private function ownedFiles(): array
    {
        $names = scandir($this->entryDirectory());
        $files = [];
        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $files[] = $name;
        }
        return $files;
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $name);
        }
        rmdir($path);
    }
}
