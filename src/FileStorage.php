<?php

declare(strict_types=1);

namespace Wherd\Cache;

use RuntimeException;

/** Cache file storage. */
class FileStorage implements IStorage
{
    public const HEADER_LEN = 4;
    public const FILE = 'file';
    public const HANDLE = 'handle';
    public const EXPIRES = 'expires';
    public const SERIALIZED = 'serialized';

    /** @var array<mixed> */
    protected array $locks = [];

    public function __construct(protected string $dir)
    {
        if (!(is_dir($dir) || mkdir($dir, 0777, true)) && !is_writable($dir)) {
            throw new RuntimeException("Directory '$dir' not found or not writable.");
        }
    }

    public function read(string $key): mixed
    {
        $meta = $this->readMetaAndLock($this->getCacheFile($key));

        return $meta && $this->verify($meta)
            ? $this->readData($meta) // calls fclose()
            : null;
    }

    /**
     * @param array<string> $keys
     * @return array<string,mixed>
     */
    public function bulkRead(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->read($key);
        }

        return $result;
    }

    public function lock(string $key): void
    {
        $cacheFile = $this->getCacheFile($key);

        if (!is_dir($dir = dirname($cacheFile))) {
            mkdir($dir);
        }

        $handle = fopen($cacheFile, 'c+b');

        if ($handle) {
            $this->locks[$key] = $handle;
            flock($handle, LOCK_EX);
        }
    }

    public function write(string $key, mixed $data, int $expires=0): void
    {
        $meta = [];

        if (0 !== $expires) {
            $meta[self::EXPIRES] = time() + $expires; // absolute time
        }

        if (!isset($this->locks[$key])) {
            $this->lock($key);

            if (!isset($this->locks[$key])) {
                return;
            }
        }

        $handle = $this->locks[$key];
        unset($this->locks[$key]);

        $cacheFile = $this->getCacheFile($key);
        ftruncate($handle, 0);

        if (!is_string($data)) {
            $data = serialize($data);
            $meta[self::SERIALIZED] = true;
        }

        $header = serialize($meta);
        $headerLength = strlen($header);

        do {
            if (self::HEADER_LEN !== fwrite($handle, pack('i', $headerLength))) {
                break;
            }

            if (fwrite($handle, $header) !== $headerLength) {
                break;
            }

            if (fwrite($handle, $data) !== strlen($data)) {
                break;
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            return;
        } while (false); // @phpstan-ignore-line

        $this->delete($cacheFile, $handle);
    }

    public function remove(string $key): void
    {
        unset($this->locks[$key]);
        $this->delete($this->getCacheFile($key));
    }

    public function clean(string $pattern=''): void
    {
        $pattern = $pattern ? $pattern . '*' : '*';
        $entries = glob($this->getCacheFile($pattern));

        foreach ($entries ?: [] as $entry) {
            if (is_dir($entry)) {
                $this->clean(str_replace($this->dir . DIRECTORY_SEPARATOR, '', $entry . DIRECTORY_SEPARATOR . '*'));
            } else {
                $this->delete($entry);
            }
        }
    }

    /** @param array<string,mixed> $meta */
    protected function verify(array $meta): bool
    {
        if (!isset($meta[self::EXPIRES]) || $meta[self::EXPIRES] > time()) {
            return true;
        }

        // meta[handle] & meta[file] was added by readMetaAndLock()
        $this->delete($meta[self::FILE], $meta[self::HANDLE]);
        return false;
    }

    /** @return array<string,mixed> */
    protected function readMetaAndLock(string $file): array
    {
        $meta = [];

        try {
            $handle = fopen($file, 'r+b');

            if (!$handle) {
                return $meta;
            }

            flock($handle, LOCK_SH);

            $metaSize = unpack('i', (string) stream_get_contents($handle, self::HEADER_LEN));

            if (!empty($metaSize)) {
                $meta = (string) stream_get_contents($handle, reset($metaSize));
                $meta = unserialize($meta);
                $meta[self::FILE] = $file;
                $meta[self::HANDLE] = $handle;
            }

            flock($handle, LOCK_UN);
            fclose($handle);
        } catch (\Throwable $e) {
        }

        return $meta;
    }

    /** @param array<string, mixed> $meta */
    protected function readData(array $meta): mixed
    {
        $data = (string) stream_get_contents($meta[self::HANDLE]);

        flock($meta[self::HANDLE], LOCK_UN);
        fclose($meta[self::HANDLE]);

        return isset($meta[self::SERIALIZED])
            ? unserialize($data)
            : $data;
    }

    protected function getCacheFile(string $key): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . str_replace('-', DIRECTORY_SEPARATOR, $key);
    }

    protected function delete(string $file, mixed $handle=null): void
    {
        if (unlink($file)) {
            if ($handle) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }

            return;
        }

        if (!$handle) {
            $handle = fopen($file, 'r+');
        }

        if ($handle) {
            fclose($handle);
            unlink($file);
        }
    }
}
