<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

interface DevolverPagoUseCase
{
    public function execute(string $pagoId): void;
}
