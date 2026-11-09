<?php
declare(strict_types=1);

namespace App\Application\Dto;

/** Resultado del caso de uso CobrarRacion. $pasarela es null si el cobro no pasó por la pasarela (beca o efectivo). Inmutable. */
final class CobroRacionResult
{
    public function __construct(
        public readonly string $pagoId,
        public readonly string $ticketId,
        public readonly string $estado,
        public readonly ?DatosCobroPasarela $pasarela = null,
    ) {}
}
