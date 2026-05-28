<?php

declare(strict_types=1);

namespace TryFit\Db;

use PDO;

// Self-heal: load AppConfig if router.php on the server is an older version
// that doesn't require it before this file is included.
if (!class_exists('TryFit\AppConfig', false)) {
    require_once dirname(__DIR__) . '/config/AppConfig.php';
}

use TryFit\AppConfig;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                AppConfig::dbHost(),
                AppConfig::dbName(),
                AppConfig::dbCharset()
            );

            self::$instance = new PDO(
                $dsn,
                AppConfig::dbUser(),
                AppConfig::dbPass(),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            self::$instance->exec("SET time_zone = '+05:30'");
        }

        return self::$instance;
    }
}
