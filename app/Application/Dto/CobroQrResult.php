<?php
declare(strict_types=1);

namespace App\Application\Dto;

/** Resultado de pedir un cobro por QR a la pasarela de pagos. Inmutable. */
final class CobroQrResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $referencia = null,
        public readonly ?string $paymentUrl = null,
        public readonly ?string $qrDataUri = null,
        public readonly ?string $error = null,
    ) {}

    public static function exito(string $referencia, ?string $paymentUrl = null, ?string $qrDataUri = null): self
    {
        return new self(true, $referencia, $paymentUrl, $qrDataUri);
    }

    public static function fallo(string $error): self
    {
        return new self(false, error: $error);
    }
}
