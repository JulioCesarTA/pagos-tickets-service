<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\QrBancario;

use App\Application\Dto\{CobroQrResult, FacturaResult, VerificacionPagoResult};
use App\Application\Ports\Outbound\PaymentGatewayPort;

/**
 * Adaptador fake de pasarela QR bancaria (reemplazar con integración real: Banco Nacional, BCP, etc.).
 */
final class FakeQrBancarioAdapter implements PaymentGatewayPort
{
    public function cobrarQr(float $monto, string $referencia): CobroQrResult
    {
        // En producción: HTTP call a la pasarela real.
        return CobroQrResult::exito("QR-$referencia-" . bin2hex(random_bytes(4)));
    }

    public function devolver(string $referencia): bool
    {
        return true;
    }

    public function facturaUrl(string $referencia): FacturaResult
    {
        return FacturaResult::noDisponible('Factura no disponible: Stripe no está configurado');
    }

    public function verificarPago(string $referencia): VerificacionPagoResult
    {
        // Fake sin Stripe: siempre lo da por pagado (modo dev)
        return new VerificacionPagoResult(paid: true, status: 'paid', sessionId: 'fake-' . substr($referencia, 0, 8));
    }
}
