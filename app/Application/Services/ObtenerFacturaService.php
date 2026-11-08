<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\ObtenerFacturaUseCase;
use App\Application\Ports\Outbound\PaymentGatewayPort;

final class ObtenerFacturaService implements ObtenerFacturaUseCase
{
    public function __construct(private readonly PaymentGatewayPort $gateway) {}

    public function execute(string $referencia): array { return $this->gateway->facturaUrl($referencia); }
}
