<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

interface ObtenerFacturaUseCase
{
    /** @return array{url?:string, tipo?:string, error?:string} */
    public function execute(string $referencia): array;
}
