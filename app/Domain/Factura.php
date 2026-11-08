<?php
declare(strict_types=1);

namespace App\Domain;

final class Factura
{
    public function __construct(
        public readonly string $id,
        public readonly string $nit,
        public readonly string $razonSocial,
        public readonly float $monto,
        public readonly \DateTimeImmutable $fecha,
        public readonly array $items = [],
    ) {}
}
