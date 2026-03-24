<?php

declare(strict_types=1);

namespace WCDO\Services;

use InvalidArgumentException;
use WCDO\Entities\Client;
use WCDO\Repositories\ClientRepository;

class AuthService
{
    private ClientRepository $clientRepo;

    public function __construct()
    {
        $this->clientRepo = new ClientRepository();
    }

    public function register(string $prenom, string $nom, string $email, string $motDePasse): Client
    {
        // Vérifier unicité de l'email avant d'insérer
        if ($this->clientRepo->findByEmail($email) !== null) {
            throw new InvalidArgumentException('Cet email est déjà utilisé.');
        }

        // PASSWORD_BCRYPT = bcrypt, coût = 10 par défaut — lent volontairement (sécurité brute-force)
        $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

        $client = $this->clientRepo->create($prenom, $nom, $email, $hash);

        $this->demarrerSession();
        $_SESSION['client_id'] = $client->getId();

        return $client;
    }

    public function login(string $email, string $motDePasse): Client
    {
        $client = $this->clientRepo->findByEmail($email);

        // Message volontairement vague — ne pas révéler si l'email existe en BDD
        if ($client === null || !$client->verifierMotDePasse($motDePasse)) {
            throw new InvalidArgumentException('Email ou mot de passe incorrect.');
        }

        $this->demarrerSession();
        $_SESSION['client_id'] = $client->getId();

        return $client;
    }

    public function logout(): void
    {
        $this->demarrerSession();
        unset($_SESSION['client_id']);
    }

    public function getClientConnecte(): ?Client
    {
        $this->demarrerSession();

        if (!isset($_SESSION['client_id'])) {
            return null;
        }

        return $this->clientRepo->findById((int)$_SESSION['client_id']);
    }

    // Démarre la session PHP uniquement si elle n'est pas déjà active
    private function demarrerSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
