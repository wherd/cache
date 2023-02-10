# Database

Yet another cache wrapper.

## Installation

Install using composer:

```bash
composer require wherd/cache
```

# Usage

```php
use Wherd\Cache\SqliteStorage;

$cache = new SqliteStorage();
$cache->write('username', 'wherd', 60);
echo $cache->read('username');
```