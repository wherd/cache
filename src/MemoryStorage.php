<?php

declare(strict_types=1);

namespace Wherd\Cache;

class MemoryStorage implements IStorage
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function read(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @param array<string> $keys
     * @return array<string,mixed>
     */
    public function bulkRead(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function lock(string $key): void
    {
    }

    public function write(string $key, mixed $data, int $expires=0): void
    {
        $this->data[$key] = $data;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clean(string $pattern=''): void
    {
        if ('' === $pattern) {
            $this->data = [];
        } else {
            $this->data = array_filter($this->data, fn ($key) => 0 !== strpos($key, $pattern), ARRAY_FILTER_USE_KEY);
        }
    }
}
