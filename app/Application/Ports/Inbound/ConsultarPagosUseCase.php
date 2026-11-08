<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

use App\Domain\Pago;

interface ConsultarPagosUseCase
{
    /** @return Pago[] */
    public function listar(): array;
    public function ver(string $pagoId): ?Pago;
}
