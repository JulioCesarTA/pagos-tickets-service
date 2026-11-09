<?php
declare(strict_types=1);

namespace App\Application\Dto;

/** Lo que el cliente necesita para completar un cobro en la pasarela externa: URL de pago, QR y referencia. Inmutable. */
final class DatosCobroPasarela
{
    public function __construct(
        public readonly ?string $paymentUrl,
        public readonly ?string $qrDataUri,
        public readonly ?string $referencia,
    ) {}
}
