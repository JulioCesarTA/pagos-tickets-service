<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\{CobroRacionResult, DatosCobroPasarela};
use App\Application\Ports\Inbound\CobrarRacionUseCase;
use App\Application\Ports\Outbound\{IdGeneratorPort, PagoRepositoryPort, PaymentGatewayPort};
use App\Domain\Pago;

/**
 * Paso 3 de la Saga 1: aplica beca o cobra.
 *  - Beca o efectivo: se cobra en el acto (ticket + PagoRealizadoEvent).
 *  - QR bancario: se crea el cobro en la pasarela y el pago queda PENDIENTE. Se cobra recién cuando la pasarela
 *    confirma que el estudiante pagó (VerificarPagoService). Así un QR generado y abandonado no cuenta como ingreso.
 */
final class CobrarRacionService implements CobrarRacionUseCase
{
    public function __construct(
        private readonly PagoRepositoryPort $pagos,
        private readonly PaymentGatewayPort $gateway,
        private readonly IdGeneratorPort $ids,
        private readonly CompletarCobro $completar,
    ) {}

    public function execute(string $reservaId, string $estudianteId, float $monto, string $metodo, ?string $sagaId = null): CobroRacionResult
    {
        $pago = Pago::nuevo($this->ids->nuevoId(), $reservaId, $estudianteId, $monto, $metodo, new \DateTimeImmutable());

        if ($metodo !== Pago::METODO_QR) {
            $ticketId = $this->completar->ejecutar($pago, $sagaId);
            return new CobroRacionResult($pago->id, $ticketId, $pago->estado, null);
        }

        $res = $this->gateway->cobrarQr($pago->monto, $pago->id);
        if (!$res->ok) {
            $pago->fallar();
            $this->pagos->save($pago);
            throw new \RuntimeException('Cobro QR fallido: ' . ($res->error ?? 'desconocido'));
        }
        $pago->asociarReferenciaPasarela($res->referencia);   // con ella se verifica y, si hace falta, se devuelve
        $this->pagos->save($pago);
        return new CobroRacionResult($pago->id, null, $pago->estado,
            new DatosCobroPasarela($res->paymentUrl, $res->qrDataUri, $res->referencia));
    }
}
