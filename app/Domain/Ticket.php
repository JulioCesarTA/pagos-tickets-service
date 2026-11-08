<?php
declare(strict_types=1);

namespace App\Domain;

final class Ticket
{
    public function __construct(
        public readonly string $id,
        public readonly string $pagoId,
        public readonly string $reservaId,
        public readonly float $monto,
        public readonly \DateTimeImmutable $emitidoEn,
    ) {
        if ($monto < 0) throw new \DomainException('Monto negativo');
    }
}
