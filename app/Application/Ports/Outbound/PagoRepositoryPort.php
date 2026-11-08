<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Pago;

interface PagoRepositoryPort
{
    public function save(Pago $pago): void;
    public function findById(string $id): ?Pago;
    public function findByReservaId(string $reservaId): ?Pago;
    /** @return Pago[] todos los pagos, del más antiguo al más reciente */
    public function todos(): array;
}
