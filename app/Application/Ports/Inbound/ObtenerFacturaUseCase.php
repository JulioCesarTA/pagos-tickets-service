<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

use App\Application\Dto\FacturaResult;

interface ObtenerFacturaUseCase
{
    public function execute(string $referencia): FacturaResult;
}
