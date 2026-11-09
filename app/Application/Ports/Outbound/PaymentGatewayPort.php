<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\{CobroQrResult, DevolucionResult, FacturaResult, VerificacionPagoResult};

interface PaymentGatewayPort
{
    public function cobrarQr(float $monto, string $referencia): CobroQrResult;
    /**
     * Devuelve el cobro identificado por la referencia que dio cobrarQr(): si ya se pagó, reembolsa el dinero;
     * si no, lo anula para que no se pueda pagar. Idempotente: repetirlo no devuelve dos veces.
     */
    public function devolver(string $referencia): DevolucionResult;
    public function verificarPago(string $referencia): VerificacionPagoResult;
    /** Factura (o recibo) hospedada por la pasarela. */
    public function facturaUrl(string $referencia): FacturaResult;
}
