<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

interface CobrarRacionUseCase
{
    /** @return array{pagoId:string, ticketId:string, estado:string} */
    public function execute(string $reservaId, string $estudianteId, float $monto, string $metodo, ?string $sagaId = null): array;
}
