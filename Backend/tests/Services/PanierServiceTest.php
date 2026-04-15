<?php

declare(strict_types=1);

namespace WCDO\Tests\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCDO\Entities\Produit;
use WCDO\Repositories\PanierProduitRepository;
use WCDO\Repositories\PanierRepository;
use WCDO\Repositories\ProduitRepository;
use WCDO\Repositories\TailleBoissonRepository;
use WCDO\Services\PanierService;

class PanierServiceTest extends TestCase
{
    // ─── Helper injection ────────────────────────────────────────────────────

    /**
     * Crée un PanierService sans appeler son constructeur (qui contacte la BDD),
     * puis injecte les mocks via Reflection pour isoler complètement les tests.
     */
    private function creerServiceAvecMocks(
        MockObject $produitRepo,
        MockObject $panierRepo,
        MockObject $panierProduitRepo,
        MockObject $tailleBoissonRepo,
    ): PanierService {
        // newInstanceWithoutConstructor() évite l'appel à Database::getInstance()
        $ref     = new \ReflectionClass(PanierService::class);
        $service = $ref->newInstanceWithoutConstructor();

        $injections = [
            'produitRepo'       => $produitRepo,
            'panierRepo'        => $panierRepo,
            'panierProduitRepo' => $panierProduitRepo,
            'tailleBoissonRepo' => $tailleBoissonRepo,
        ];

        foreach ($injections as $nomProp => $mock) {
            $prop = $ref->getProperty($nomProp);
            $prop->setAccessible(true);
            $prop->setValue($service, $mock);
        }

        return $service;
    }

    /** Crée un produit disponible (stock > 0) */
    private function creerProduit(int $stock = 5, float $prix = 5.00): Produit
    {
        return new Produit(1, 'Test', 'Desc', $prix, $stock, 1, null, '2026-01-01 00:00:00');
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    /**
     * RG-001 : un produit avec stock=0 → estDisponible()=false → exception à l'ajout
     */
    public function testAjouterProduitIndisponibleLeveException(): void
    {
        $produitRepo       = $this->createMock(ProduitRepository::class);
        $panierRepo        = $this->createMock(PanierRepository::class);
        $panierProduitRepo = $this->createMock(PanierProduitRepository::class);
        $tailleBoissonRepo = $this->createMock(TailleBoissonRepository::class);

        // Produit existe mais stock = 0 → estDisponible() retourne false
        $produitRepo->method('findById')->willReturn($this->creerProduit(stock: 0));

        $service = $this->creerServiceAvecMocks($produitRepo, $panierRepo, $panierProduitRepo, $tailleBoissonRepo);

        $this->expectException(\InvalidArgumentException::class);

        $service->ajouter('session-abc', 1, 1);
    }

    /**
     * RG-001 : si findById() retourne null, le produit est introuvable → exception
     */
    public function testAjouterProduitInexistantLeveException(): void
    {
        $produitRepo       = $this->createMock(ProduitRepository::class);
        $panierRepo        = $this->createMock(PanierRepository::class);
        $panierProduitRepo = $this->createMock(PanierProduitRepository::class);
        $tailleBoissonRepo = $this->createMock(TailleBoissonRepository::class);

        // findById() retourne null → produit inconnu en BDD
        $produitRepo->method('findById')->willReturn(null);

        $service = $this->creerServiceAvecMocks($produitRepo, $panierRepo, $panierProduitRepo, $tailleBoissonRepo);

        $this->expectException(\InvalidArgumentException::class);

        $service->ajouter('session-abc', 99, 1);
    }

    /**
     * RG-002 : maximum 2 sauces autorisées par menu — 3 sauces → exception
     */
    public function testMaxDeuxSaucesRG002(): void
    {
        $produitRepo       = $this->createMock(ProduitRepository::class);
        $panierRepo        = $this->createMock(PanierRepository::class);
        $panierProduitRepo = $this->createMock(PanierProduitRepository::class);
        $tailleBoissonRepo = $this->createMock(TailleBoissonRepository::class);

        // Produit disponible : le check des sauces n'est atteint qu'après validation du produit
        $produitRepo->method('findById')->willReturn($this->creerProduit(stock: 5));

        $service = $this->creerServiceAvecMocks($produitRepo, $panierRepo, $panierProduitRepo, $tailleBoissonRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/2 sauces/i');

        // 3 sauces dans les détails → viole RG-002
        $service->ajouter('session-abc', 1, 1, ['sauces' => [1, 2, 3]]);
    }
}
