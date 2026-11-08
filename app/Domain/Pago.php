<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Entidad de dominio Pago. PHP puro, sin dependencias de framework.
 */
final class Pago
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_COBRADO = 'cobrado';
    public const ESTADO_DEVUELTO = 'devuelto';
    public const ESTADO_FALLIDO = 'fallido';

    public const METODO_QR = 'qr_bancario';
    public const METODO_EFECTIVO = 'efectivo';
    public const METODO_BECA = 'beca';

    public function __construct(
        public readonly string $id,
        public readonly string $reservaId,
        public readonly string $estudianteId,
        public readonly float $monto,
        public readonly string $metodo,
        public string $estado,
        public readonly \DateTimeImmutable $createdAt,
        public ?string $ticketId = null,
    ) {
        if ($monto < 0) throw new \DomainException('Monto negativo no válido');
        if (!in_array($metodo, [self::METODO_QR, self::METODO_EFECTIVO, self::METODO_BECA], true)) {
            throw new \DomainException("Método inválido: $metodo");
        }
    }

    public function cobrar(string $ticketId): void
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \DomainException("No se cobra desde estado $this->estado");
        }
        $this->estado = self::ESTADO_COBRADO;
        $this->ticketId = $ticketId;
    }

    public function fallar(): void
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \DomainException("No se falla desde $this->estado");
        }
        $this->estado = self::ESTADO_FALLIDO;
    }

    public function devolver(): void
    {
        if ($this->estado !== self::ESTADO_COBRADO) {
            throw new \DomainException("Solo se devuelve un pago cobrado (estado=$this->estado)");
        }
        $this->estado = self::ESTADO_DEVUELTO;
    }
}
