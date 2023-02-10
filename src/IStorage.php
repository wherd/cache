<?php

declare(strict_types=1);

namespace Wherd\Cache;

interface IStorage
{
    /** Read from cache. */
    public function read(string $key): mixed;

    /**
     * Reads from cache in bulk.
     * @param array<string> $keys
     * @return array<string,mixed>
     */
    public function bulkRead(array $keys): array;

    /** Prevents item reading and writing. Lock is released by write() or remove(). */
    public function lock(string $key): void;

    /**  Writes item into the cache. */
    public function write(string $key, mixed $data, int $expires=0): void;

    /** Removes item from the cache. */
    public function remove(string $key): void;

    /** Removes items from the cache by pattern. */
    public function clean(string $pattern=''): void;
}
