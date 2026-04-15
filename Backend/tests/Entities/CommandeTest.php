<?php

declare(strict_types=1);

namespace WCDO\Tests\Entities;

use PHPUnit\Framework\TestCase;
use WCDO\Entities\Commande;

class CommandeTest extends TestCase
{
    // ─── Helper ──────────────────────────────────────────────────────────────

    private function creerCommande(
        string $statut = Commande::STATUS_EN_ATTENTE,
        float $montant = 12.50,
        ?int $clientId = null
    ): Commande {
        return new Commande(
            id: 1,
            numeroCommande: 'CMD-001',
            numeroChevalet: 42,
            typeCommande: 'sur_place',
            modePaiement: 'carte',
            montantTotal: $montant,
            dateCreation: '2026-01-01 10:00:00',
            clientId: $clientId,
            statut: $statut,
        );
    }

    // ─── Tests statut par défaut ─────────────────────────────────────────────

    public function testStatutParDefautEstEnAttente(): void
    {
        // Constructeur sans paramètre statut → valeur par défaut STATUS_EN_ATTENTE
        $commande = new Commande(1, 'CMD-001', 42, 'sur_place', 'carte', 12.50, '2026-01-01', null);

        $this->assertSame(Commande::STATUS_EN_ATTENTE, $commande->getStatut());
    }

    // ─── Tests helpers de statut ─────────────────────────────────────────────

    public function testEstEnAttenteRetourneTrue(): void
    {
        $commande = $this->creerCommande(Commande::STATUS_EN_ATTENTE);

        $this->assertTrue($commande->estEnAttente());
    }

    public function testEstPrepareeRetourneTrue(): void
    {
        $commande = $this->creerCommande(Commande::STATUS_PREPAREE);

        $this->assertTrue($commande->estPreparee());
    }

    public function testEstLivreeRetourneTrue(): void
    {
        $commande = $this->creerCommande(Commande::STATUS_LIVREE);

        $this->assertTrue($commande->estLivree());
    }

    public function testEstEnAttenteRetourneFalseSiPreparee(): void
    {
        $commande = $this->creerCommande(Commande::STATUS_PREPAREE);

        $this->assertFalse($commande->estEnAttente());
    }

    // ─── Tests points fidélité (RG-005) ─────────────────────────────────────

    /** RG-005 : 1€ dépensé = 1 point, arrondi à l'inférieur (floor) */
    public function testCalculerPointsFidelite1EuroPourUnPoint(): void
    {
        $commande = $this->creerCommande(montant: 12.80);

        // floor(12.80) = 12
        $this->assertSame(12, $commande->calculerPointsFidelite());
    }

    public function testCalculerPointsFideliteArrondiInferieur(): void
    {
        $commande = $this->creerCommande(montant: 9.99);

        // floor(9.99) = 9 — jamais arrondi au supérieur
        $this->assertSame(9, $commande->calculerPointsFidelite());
    }

    // ─── Tests client anonyme (RG-009) ───────────────────────────────────────

    /** RG-009 : un client anonyme (clientId = null) est un cas normal, pas une erreur */
    public function testClientAnonymeEstAccepte(): void
    {
        // Ne doit pas lancer d'exception
        $commande = $this->creerCommande(clientId: null);

        $this->assertNull($commande->getClientId());
    }

    // ─── Tests toArray ───────────────────────────────────────────────────────

    public function testToArrayContientStatut(): void
    {
        $commande = $this->creerCommande(Commande::STATUS_PREPAREE);

        $this->assertArrayHasKey('statut', $commande->toArray());
    }
}
