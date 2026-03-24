<?php

declare(strict_types=1);

namespace WCDO\Entities;

class TailleBoisson
{
    public function __construct(
        private readonly int    $id,
        private readonly string $nom,
        private readonly int    $volume,
        private readonly float  $supplementPrix,
    ) {}

    public function getId(): int              { return $this->id; }
    public function getNom(): string          { return $this->nom; }
    public function getVolume(): int          { return $this->volume; }
    public function getSupplementPrix(): float { return $this->supplementPrix; }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'nom'             => $this->nom,
            'volume'          => $this->volume,
            // Pont entre camelCase PHP et snake_case BDD/JSON
            'supplement_prix' => $this->supplementPrix,
        ];
    }
}
