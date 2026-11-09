<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

use App\Application\Dto\VerificacionPagoResult;

interface VerificarPagoUseCase
{
    public function execute(string $referencia): VerificacionPagoResult;
}
