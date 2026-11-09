<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

use App\Domain\Pago;

interface ConsultarPagosUseCase
{
    /** @return Pago[] todos, o solo los del estudiante indicado */
    public function listar(?string $estudianteId = null): array;
    public function ver(string $pagoId): ?Pago;
}
