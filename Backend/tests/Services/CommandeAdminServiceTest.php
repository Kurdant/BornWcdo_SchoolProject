<?php

declare(strict_types=1);

namespace WCDO\Tests\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCDO\Entities\Commande;
use WCDO\Repositories\CommandeRepository;
use WCDO\Services\CommandeAdminService;

class CommandeAdminServiceTest extends TestCase
{
    // ─── Helper injection ────────────────────────────────────────────────────

    /**
     * Crée un CommandeAdminService sans son constructeur (évite Database::getInstance()),
     * puis injecte le mock CommandeRepository via Reflection.
     */
    private function creerServiceAvecMock(MockObject $repo): CommandeAdminService
    {
        $ref     = new \ReflectionClass(CommandeAdminService::class);
        $service = $ref->newInstanceWithoutConstructor();

        $prop = $ref->getProperty('commandeRepo');
        $prop->setAccessible(true);
        $prop->setValue($service, $repo);

        return $service;
    }

    /** Helper : construit une Commande avec le statut donné */
    private function creerCommande(string $statut = Commande::STATUS_EN_ATTENTE): Commande
    {
        return new Commande(
            id: 1,
            numeroCommande: 'CMD-001',
            numeroChevalet: 42,
            typeCommande: 'sur_place',
            modePaiement: 'carte',
            montantTotal: 12.50,
            dateCreation: '2026-01-01 10:00:00',
            clientId: null,
            statut: $statut,
        );
    }

    // ─── Tests marquerPreparee ────────────────────────────────────────────────

    /**
     * Transition valide : en_attente → preparee
     * updateStatut doit être appelé exactement une fois avec STATUS_PREPAREE.
     */
    public function testMarquerPrepareeCommandeEnAttente(): void
    {
        $repo = $this->createMock(CommandeRepository::class);

        $commandeEnAttente = $this->creerCommande(Commande::STATUS_EN_ATTENTE);
        $commandePreparee  = $this->creerCommande(Commande::STATUS_PREPAREE);

        // findById : 1er appel → commande en attente, 2e appel (rechargement) → préparée
        $repo->method('findById')->willReturnOnConsecutiveCalls($commandeEnAttente, $commandePreparee);

        // updateStatut DOIT être appelé avec STATUS_PREPAREE
        $repo->expects($this->once())
            ->method('updateStatut')
            ->with(1, Commande::STATUS_PREPAREE);

        $service  = $this->creerServiceAvecMock($repo);
        $resultat = $service->marquerPreparee(1);

        $this->assertTrue($resultat->estPreparee());
    }

    /** Idempotence interdite : une commande déjà préparée ne peut pas redevenir "préparée" */
    public function testMarquerPrepareeCommandeDejaPrepareeLeveException(): void
    {
        $repo = $this->createMock(CommandeRepository::class);
        $repo->method('findById')->willReturn($this->creerCommande(Commande::STATUS_PREPAREE));

        $service = $this->creerServiceAvecMock($repo);

        $this->expectException(\InvalidArgumentException::class);

        $service->marquerPreparee(1);
    }

    /** Une commande livrée ne peut pas revenir en arrière */
    public function testMarquerPrepareeSurCommandeLivreeLeveException(): void
    {
        $repo = $this->createMock(CommandeRepository::class);
        $repo->method('findById')->willReturn($this->creerCommande(Commande::STATUS_LIVREE));

        $service = $this->creerServiceAvecMock($repo);

        $this->expectException(\InvalidArgumentException::class);

        $service->marquerPreparee(1);
    }

    // ─── Tests marquerLivree ──────────────────────────────────────────────────

    /**
     * Transition valide : preparee → livree
     * updateStatut doit être appelé exactement une fois avec STATUS_LIVREE.
     */
    public function testMarquerLivreeCommandePreparee(): void
    {
        $repo = $this->createMock(CommandeRepository::class);

        $commandePreparee = $this->creerCommande(Commande::STATUS_PREPAREE);
        $commandeLivree   = $this->creerCommande(Commande::STATUS_LIVREE);

        $repo->method('findById')->willReturnOnConsecutiveCalls($commandePreparee, $commandeLivree);

        $repo->expects($this->once())
            ->method('updateStatut')
            ->with(1, Commande::STATUS_LIVREE);

        $service  = $this->creerServiceAvecMock($repo);
        $resultat = $service->marquerLivree(1);

        $this->assertTrue($resultat->estLivree());
    }

    /** On ne peut pas passer directement de en_attente à livree (flux imposé : en_attente → preparee → livree) */
    public function testMarquerLivreeCommandeEnAttenteLeveException(): void
    {
        $repo = $this->createMock(CommandeRepository::class);
        $repo->method('findById')->willReturn($this->creerCommande(Commande::STATUS_EN_ATTENTE));

        $service = $this->creerServiceAvecMock($repo);

        $this->expectException(\InvalidArgumentException::class);

        $service->marquerLivree(1);
    }

    // ─── Tests commande introuvable ───────────────────────────────────────────

    /** findById() retourne null → RuntimeException (erreur technique, pas métier) */
    public function testMarquerPrepareeCommandeInexistanteLeveRuntimeException(): void
    {
        $repo = $this->createMock(CommandeRepository::class);
        $repo->method('findById')->willReturn(null);

        $service = $this->creerServiceAvecMock($repo);

        $this->expectException(\RuntimeException::class);

        $service->marquerPreparee(999);
    }
}
