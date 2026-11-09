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
        /** Referencia del cobro en la pasarela (QR): sin ella no se puede devolver el dinero. */
        public ?string $referenciaPasarela = null,
    ) {
        if ($monto < 0) throw new \DomainException('Monto negativo no válido');
        if (!in_array($metodo, [self::METODO_QR, self::METODO_EFECTIVO, self::METODO_BECA], true)) {
            throw new \DomainException("Método inválido: $metodo");
        }
    }

    /**
     * Crea un pago nuevo, pendiente de cobro. Regla de negocio: con beca el estudiante no paga (monto 0).
     */
    public static function nuevo(
        string $id,
        string $reservaId,
        string $estudianteId,
        float $monto,
        string $metodo,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            reservaId: $reservaId,
            estudianteId: $estudianteId,
            monto: $metodo === self::METODO_BECA ? 0.0 : $monto,
            metodo: $metodo,
            estado: self::ESTADO_PENDIENTE,
            createdAt: $createdAt,
        );
    }

    public function cobrar(string $ticketId): void
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \DomainException("No se cobra desde estado $this->estado");
        }
        $this->estado = self::ESTADO_COBRADO;
        $this->ticketId = $ticketId;
    }

    /** Asocia el cobro hecho en la pasarela (solo pagos por QR), para poder devolverlo después. */
    public function asociarReferenciaPasarela(string $referencia): void
    {
        if ($this->metodo !== self::METODO_QR) {
            throw new \DomainException("Solo un pago por QR tiene referencia de pasarela (metodo=$this->metodo)");
        }
        if (trim($referencia) === '') {
            throw new \DomainException('La referencia de la pasarela no puede estar vacía');
        }
        $this->referenciaPasarela = $referencia;
    }

    /** Un pago por QR solo se puede devolver si se sabe qué cobro deshacer en la pasarela. */
    public function requiereDevolucionEnPasarela(): bool
    {
        return $this->metodo === self::METODO_QR;
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
