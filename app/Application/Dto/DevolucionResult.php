<?php
declare(strict_types=1);

namespace App\Application\Dto;

/**
 * Resultado de pedir a la pasarela que devuelva un cobro. Inmutable.
 *  - reembolsado: el cobro estaba pagado y se devolvió el dinero (reembolsoId = id del reembolso en la pasarela).
 *  - sin_cobro:   nunca se llegó a pagar; se anuló el cobro para que ya no se pueda pagar.
 */
final class DevolucionResult
{
    public const REEMBOLSADO = 'reembolsado';
    public const SIN_COBRO = 'sin_cobro';

    private function __construct(
        public readonly bool $ok,
        public readonly ?string $estado = null,
        public readonly ?string $reembolsoId = null,
        public readonly ?string $error = null,
    ) {}

    public static function reembolsado(string $reembolsoId): self
    {
        return new self(true, self::REEMBOLSADO, $reembolsoId);
    }

    public static function sinCobro(): self
    {
        return new self(true, self::SIN_COBRO);
    }

    public static function fallo(string $error): self
    {
        return new self(false, error: $error);
    }
}
