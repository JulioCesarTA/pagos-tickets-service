<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\VerificarPagoUseCase;
use App\Application\Ports\Outbound\PaymentGatewayPort;

final class VerificarPagoService implements VerificarPagoUseCase
{
    public function __construct(private readonly PaymentGatewayPort $gateway) {}

    public function execute(string $referencia): array { return $this->gateway->verificarPago($referencia); }
}
