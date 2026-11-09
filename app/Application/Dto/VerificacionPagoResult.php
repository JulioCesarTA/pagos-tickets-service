<?php
declare(strict_types=1);

namespace App\Application\Dto;

/** Estado de un cobro según la pasarela. Los campos opcionales son null cuando la pasarela no los informa. Inmutable. */
final class VerificacionPagoResult
{
    public function __construct(
        public readonly bool $paid,
        public readonly string $status,
        public readonly ?int $amount = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $currency = null,
        public readonly ?string $error = null,
    ) {}

    public static function fallo(string $error): self
    {
        return new self(false, 'error', error: $error);
    }
}
