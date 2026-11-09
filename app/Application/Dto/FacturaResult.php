<?php
declare(strict_types=1);

namespace App\Application\Dto;

/** Factura (o recibo) hospedada por la pasarela, o el motivo por el que no está disponible. Inmutable. */
final class FacturaResult
{
    public const TIPO_FACTURA = 'factura';
    public const TIPO_RECIBO = 'recibo';

    private function __construct(
        public readonly ?string $url,
        public readonly ?string $tipo,
        public readonly ?string $pdf,
        public readonly ?string $error,
    ) {}

    /** Factura formal; $pdf puede ser null si la pasarela no publicó el PDF. */
    public static function factura(string $url, ?string $pdf): self { return new self($url, self::TIPO_FACTURA, $pdf, null); }

    public static function recibo(string $url): self { return new self($url, self::TIPO_RECIBO, null, null); }

    public static function noDisponible(string $error): self { return new self(null, null, null, $error); }

    public function disponible(): bool { return $this->url !== null; }
}
