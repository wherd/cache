<?php

declare(strict_types=1);

namespace Wherd\Cache;

use PDO;

class SQLiteStorage implements IStorage
{
    protected PDO $pdo;

    public function __construct(string $path=':memory:')
    {
        if (':memory:' !== $path && !is_file($path)) {
            touch($path);
        }

        $options = [];

        if (':memory:' === $path) {
            $options = [PDO::ATTR_PERSISTENT => true];
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
			CREATE TABLE IF NOT EXISTS cache (
				key TEXT NOT NULL PRIMARY KEY,
				data BLOB NOT NULL,
				expires INTEGER
			);
			CREATE INDEX IF NOT EXISTS cache_expires ON cache(expires);
			CREATE INDEX IF NOT EXISTS cache_key ON cache(key);
			PRAGMA synchronous = OFF;
        ');
    }

    public function read(string $key): mixed
    {
        $stmt = $this->pdo->prepare('SELECT data FROM cache WHERE key=? AND (expires IS NULL OR expires > ?)');
        $stmt->execute([$key, time()]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return unserialize($row['data']);
        }

        return null;
    }

    /**
     * @param array<string> $keys
     * @return array<string,mixed>
     */
    public function bulkRead(array $keys): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT key, data FROM cache WHERE key IN (?' . str_repeat(',?', count($keys) - 1) . ') AND (expires IS NULL OR expires > ?)'
        );

        $stmt->execute(array_merge($keys, [time()]));
        $result = array_fill_keys($keys, null);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string) $row['key']] = unserialize($row['data']);
        }

        return $result;
    }

    public function lock(string $key): void
    {
    }

    public function write(string $key, mixed $data, int $expires=0): void
    {
        $expires = 0 !== $expires ? $expires + time() : null;

        $this->pdo->exec('BEGIN TRANSACTION');
        $this->pdo
            ->prepare('REPLACE INTO cache (key, data, expires) VALUES (?, ?, ?)')
            ->execute([$key, serialize($data), $expires]);
        $this->pdo->exec('COMMIT');
    }

    public function remove(string $key): void
    {
        $this->pdo->prepare('DELETE FROM cache WHERE key=?')->execute([$key]);
    }

    public function clean(string $pattern=''): void
    {
        if ('' === $pattern) {
            $this->pdo->prepare('DELETE FROM cache')->execute();
        } else {
            $sql = 'DELETE FROM cache WHERE expires < ? OR key LIKE ?';
            $args = [time(), $pattern . '%'];
            $this->pdo->prepare($sql)->execute($args);
        }
    }
}
