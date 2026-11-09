<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Pago;

interface PagoRepositoryPort
{
    public function save(Pago $pago): void;
    public function findById(string $id): ?Pago;
    /** @return Pago[] todos los pagos, del más antiguo al más reciente */
    public function todos(): array;

    /** @return Pago[] los pagos de un estudiante, del más antiguo al más reciente */
    public function delEstudiante(string $estudianteId): array;
}
