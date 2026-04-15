<?php

declare(strict_types=1);

namespace WCDO\Services;

use WCDO\Entities\Commande;
use WCDO\Repositories\CommandeRepository;

/**
 * Gère les transitions de statut des commandes pour le back-office.
 *
 * Flux autorisé :
 *   en_attente → preparee  (rôle : preparation ou administration)
 *   preparee   → livree    (rôle : accueil ou administration)
 *
 * Ce Service ne fait PAS de contrôle de rôle — c'est la responsabilité du Controller.
 * Il se concentre uniquement sur la validité métier de la transition.
 */
class CommandeAdminService
{
    private CommandeRepository $commandeRepo;

    public function __construct()
    {
        $this->commandeRepo = new CommandeRepository();
    }

    /**
     * Passe une commande de 'en_attente' → 'preparee'.
     * Appelé par rôle 'preparation' ou 'administration'.
     *
     * @throws \RuntimeException         Si la commande n'existe pas.
     * @throws \InvalidArgumentException Si le statut actuel ne permet pas cette transition.
     */
    public function marquerPreparee(int $id): Commande
    {
        $commande = $this->commandeRepo->findById($id);

        if ($commande === null) {
            throw new \RuntimeException("Commande #$id introuvable");
        }

        // Transition valide uniquement depuis en_attente
        if (!$commande->estEnAttente()) {
            throw new \InvalidArgumentException(
                "La commande #$id ne peut pas être marquée préparée (statut actuel : {$commande->getStatut()})"
            );
        }

        $this->commandeRepo->updateStatut($id, Commande::STATUS_PREPAREE);

        // Recharger depuis la BDD pour retourner l'objet à jour
        return $this->commandeRepo->findById($id);
    }

    /**
     * Passe une commande de 'preparee' → 'livree'.
     * Appelé par rôle 'accueil' ou 'administration'.
     *
     * @throws \RuntimeException         Si la commande n'existe pas.
     * @throws \InvalidArgumentException Si le statut actuel ne permet pas cette transition.
     */
    public function marquerLivree(int $id): Commande
    {
        $commande = $this->commandeRepo->findById($id);

        if ($commande === null) {
            throw new \RuntimeException("Commande #$id introuvable");
        }

        // Transition valide uniquement depuis preparee
        if (!$commande->estPreparee()) {
            throw new \InvalidArgumentException(
                "La commande #$id ne peut pas être marquée livrée (statut actuel : {$commande->getStatut()})"
            );
        }

        $this->commandeRepo->updateStatut($id, Commande::STATUS_LIVREE);

        return $this->commandeRepo->findById($id);
    }
}
