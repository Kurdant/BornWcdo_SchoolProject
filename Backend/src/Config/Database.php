<?php

declare(strict_types=1);

namespace WCDO\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    // Constructeur privé : interdit d'instancier cette classe avec "new Database()"
    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? 'db',
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_NAME'] ?? 'wcdo'
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $_ENV['DB_USER'] ?? 'wcdo',
                    $_ENV['DB_PASS'] ?? 'wcdo',
                    [
                        // Retourne les résultats en tableaux associatifs par défaut
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        // Lève une exception PHP à chaque erreur SQL
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        // Désactive l'émulation : les requêtes préparées sont vraiment envoyées au serveur
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                // Ne jamais exposer les détails de connexion en dehors des logs
                throw new \RuntimeException('Connexion BDD impossible : ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
