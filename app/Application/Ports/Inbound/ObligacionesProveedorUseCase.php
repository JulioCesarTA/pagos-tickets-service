<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

use App\Domain\ObligacionProveedor;

interface ObligacionesProveedorUseCase
{
    /** Registrar dos veces la misma orden y proveedor devuelve la obligación que ya existía. */
    public function registrar(string $ordenId, string $proveedor, float $monto, array $detalle): ObligacionProveedor;

    /** @return ObligacionProveedor[] */
    public function listar(): array;
}
