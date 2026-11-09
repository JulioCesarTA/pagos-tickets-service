<?php
declare(strict_types=1);

namespace App\Domain;

/** Lo que el comedor debe pagarle a un proveedor por una orden de compra aprobada. */
final class ObligacionProveedor
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';

    /** @param string[] $detalle líneas de la orden para este proveedor */
    public function __construct(
        public readonly string $id,
        public readonly string $ordenId,
        public readonly string $proveedor,
        public readonly float $monto,
        public readonly array $detalle,
        public readonly string $estado,
        public readonly \DateTimeImmutable $creadaEn,
    ) {
        if (trim($ordenId) === '' || trim($proveedor) === '') throw new \InvalidArgumentException('Faltan la orden o el proveedor');
        if ($monto <= 0) throw new \InvalidArgumentException('El monto debe ser mayor a cero');
    }
}
