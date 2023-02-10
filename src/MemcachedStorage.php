<?php

declare(strict_types=1);

namespace Wherd\Cache;

class MemcachedStorage implements IStorage
{
    protected \Memcached $memcached;

    public function __construct(string $host='localhost', int $port=11211, protected string $prefix='')
    {
        $this->memcached = new \Memcached();

        if ($host) {
            $this->addServer($host, $port);
        }
    }

    public function addServer(string $host='localhost', int $port=11211): void
    {
        if (false === $this->memcached->addServer($host, $port, 1)) {
            $error = error_get_last() ?? ['message' => 'Unknown error'];
            throw new \RuntimeException("Memcached::addServer(): {$error['message']}.");
        }
    }

    public function getConnection(): \Memcached
    {
        return $this->memcached;
    }

    public function read(string $key): mixed
    {
        $key = urlencode($this->prefix . $key);
        return $this->memcached->get($key) ?: null;
    }

    /**
     * @param array<string> $keys
     * @return array<string,mixed>
     */
    public function bulkRead(array $keys): array
    {
        $prefixedKeys = array_map(fn ($key) => urlencode($this->prefix . $key), $keys) ?: [];

        $keys = array_combine($prefixedKeys, $keys) ?: [];
        $values = $this->memcached->getMulti($prefixedKeys) ?: [];
        $result = array_fill_keys($keys, null);

        foreach ($values as $prefixedKey => $value) {
            $result[$keys[$prefixedKey]] = $value;
        }

        return $result;
    }

    public function lock(string $key): void
    {
    }

    public function write(string $key, mixed $data, int $expires=0): void
    {
        $key = urlencode($this->prefix . $key);
        $this->memcached->set($key, $data, $expires);
    }

    public function remove(string $key): void
    {
        $this->memcached->delete(urlencode($this->prefix . $key), 0);
    }

    public function clean(string $pattern=''): void
    {
        if ('' === $pattern) {
            $this->memcached->flush();
            return;
        }

        $keys = $this->memcached->getAllKeys();

        if (empty($keys)) {
            return;
        }

        $deleteKeys = array_filter($keys, fn ($key) => false !== strpos($key, $pattern));

        if (empty($deleteKeys)) {
            return;
        }

        $prefixedKeys = array_map(fn ($key) =>  urlencode($this->prefix . $key), $deleteKeys);
        $this->memcached->deleteMulti($prefixedKeys, 0);
    }
}
