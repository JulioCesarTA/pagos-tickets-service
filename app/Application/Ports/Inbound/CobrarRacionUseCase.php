<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

use App\Application\Dto\CobroRacionResult;

interface CobrarRacionUseCase
{
    public function execute(string $reservaId, string $estudianteId, float $monto, string $metodo, ?string $sagaId = null): CobroRacionResult;
}
