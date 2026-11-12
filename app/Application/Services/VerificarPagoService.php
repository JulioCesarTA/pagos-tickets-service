<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\VerificacionPagoResult;
use App\Application\Ports\Inbound\VerificarPagoUseCase;
use App\Application\Ports\Outbound\{PagoRepositoryPort, PaymentGatewayPort};
use App\Domain\Pago;

/**
 * Consulta a la pasarela si el cobro QR se pagó. Si se pagó y el pago todavía está PENDIENTE, lo cobra
 * (ticket + PagoRealizadoEvent). Es idempotente: verificar otra vez un pago ya cobrado no vuelve a cobrarlo.
 */
final class VerificarPagoService implements VerificarPagoUseCase
{
    public function __construct(
        private readonly PaymentGatewayPort $gateway,
        private readonly PagoRepositoryPort $pagos,
        private readonly CompletarCobro $completar,
    ) {}

    public function execute(string $referencia): VerificacionPagoResult
    {
        $r = $this->gateway->verificarPago($referencia);
        if ($r->paid) {
            $pago = $this->pagos->findByReferenciaPasarela($referencia);
            if ($pago !== null && $pago->estado === Pago::ESTADO_PENDIENTE) {
                $this->completar->ejecutar($pago);
            }
        }
        return $r;
    }
}
