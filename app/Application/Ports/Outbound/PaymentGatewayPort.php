<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\{CobroQrResult, FacturaResult, VerificacionPagoResult};

interface PaymentGatewayPort
{
    public function cobrarQr(float $monto, string $referencia): CobroQrResult;
    public function devolver(string $referencia): bool;
    public function verificarPago(string $referencia): VerificacionPagoResult;
    /** Factura (o recibo) hospedada por la pasarela. */
    public function facturaUrl(string $referencia): FacturaResult;
}
