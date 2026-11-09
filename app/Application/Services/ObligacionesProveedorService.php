<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\ObligacionesProveedorUseCase;
use App\Application\Ports\Outbound\ObligacionRepositoryPort;
use App\Domain\ObligacionProveedor;
use Ramsey\Uuid\Uuid;

final class ObligacionesProveedorService implements ObligacionesProveedorUseCase
{
    public function __construct(private readonly ObligacionRepositoryPort $obligaciones) {}

    public function registrar(string $ordenId, string $proveedor, float $monto, array $detalle): ObligacionProveedor
    {
        return $this->obligaciones->guardarSiNoExiste(new ObligacionProveedor(
            Uuid::uuid4()->toString(), $ordenId, $proveedor, $monto, $detalle,
            ObligacionProveedor::ESTADO_PENDIENTE, new \DateTimeImmutable(),
        ));
    }

    public function listar(): array { return $this->obligaciones->todas(); }
}
