<?php

declare(strict_types=1);

namespace WCDO\Tests\Entities;

use PHPUnit\Framework\TestCase;
use WCDO\Entities\Menu;

class MenuTest extends TestCase
{
    // ─── Helper ──────────────────────────────────────────────────────────────

    private function creerMenu(
        float $prix = 9.90,
        bool $disponible = true,
        array $produits = []
    ): Menu {
        return new Menu(
            id: 1,
            nom: 'Menu Test',
            description: 'Un menu de test',
            prix: $prix,
            image: null,
            disponible: $disponible,
            dateCreation: '2026-01-01 00:00:00',
            produits: $produits,
        );
    }

    // ─── Tests disponibilité ─────────────────────────────────────────────────

    public function testMenuDisponibleRetourneTrue(): void
    {
        $menu = $this->creerMenu(disponible: true);

        $this->assertTrue($menu->estDisponible());
    }

    public function testMenuIndisponibleRetourneFalse(): void
    {
        $menu = $this->creerMenu(disponible: false);

        $this->assertFalse($menu->estDisponible());
    }

    // ─── Tests validation prix ───────────────────────────────────────────────

    public function testPrixNegatifLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->creerMenu(prix: -5.00);
    }

    // ─── Tests produits ──────────────────────────────────────────────────────

    public function testProduitsVideParDefaut(): void
    {
        $menu = $this->creerMenu(produits: []);

        $this->assertSame([], $menu->getProduits());
    }

    public function testProduitsRetourneTableauFourni(): void
    {
        $produits = [
            ['id_produit' => 1, 'quantite' => 2],
            ['id_produit' => 3, 'quantite' => 1],
        ];

        $menu = $this->creerMenu(produits: $produits);

        $this->assertSame($produits, $menu->getProduits());
    }

    // ─── Tests toArray ───────────────────────────────────────────────────────

    public function testToArrayContientProduits(): void
    {
        $menu = $this->creerMenu();

        $this->assertArrayHasKey('produits', $menu->toArray());
    }
}
