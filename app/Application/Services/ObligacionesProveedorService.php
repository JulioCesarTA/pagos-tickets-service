<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\ObligacionesProveedorUseCase;
use App\Application\Ports\Outbound\{IdGeneratorPort, ObligacionRepositoryPort};
use App\Domain\ObligacionProveedor;

final class ObligacionesProveedorService implements ObligacionesProveedorUseCase
{
    public function __construct(
        private readonly ObligacionRepositoryPort $obligaciones,
        private readonly IdGeneratorPort $ids,
    ) {}

    public function registrar(string $ordenId, string $proveedor, float $monto, array $detalle): ObligacionProveedor
    {
        return $this->obligaciones->guardarSiNoExiste(new ObligacionProveedor(
            $this->ids->nuevoId(), $ordenId, $proveedor, $monto, $detalle,
            ObligacionProveedor::ESTADO_PENDIENTE, new \DateTimeImmutable(),
        ));
    }

    public function listar(): array { return $this->obligaciones->todas(); }
}
