<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\DevolverPagoUseCase;
use App\Application\Ports\Outbound\{PagoRepositoryPort, PaymentGatewayPort, EventBusPort};

final class DevolverPagoService implements DevolverPagoUseCase
{
    public function __construct(
        private readonly PagoRepositoryPort $pagos,
        private readonly PaymentGatewayPort $gateway,
        private readonly EventBusPort $bus,
    ) {}

    /**
     * Devuelve un pago. Si se cobró por QR, primero se devuelve el dinero en la pasarela; si eso falla
     * (o no se sabe qué cobro deshacer), el pago NO se marca como devuelto y se informa el error.
     */
    public function execute(string $pagoId): void
    {
        $pago = $this->pagos->findById($pagoId);
        if (!$pago) throw new \RuntimeException("Pago no existe: $pagoId");

        $reembolso = null;
        if ($pago->requiereDevolucionEnPasarela()) {
            if ($pago->referenciaPasarela === null) {
                throw new \RuntimeException("El pago $pagoId no tiene referencia de la pasarela: hay que devolverlo a mano");
            }
            $r = $this->gateway->devolver($pago->referenciaPasarela);
            if (!$r->ok) {
                throw new \RuntimeException('La pasarela no pudo devolver el pago: ' . ($r->error ?? 'error desconocido'));
            }
            $reembolso = $r->reembolsoId;
        }

        $pago->devolver();
        $this->pagos->save($pago);
        $this->bus->publish('dominio-pagos', [
            'type' => 'PagoDevueltoEvent',
            'pagoId' => $pago->id,
            'reservaId' => $pago->reservaId,
            'reembolsoId' => $reembolso,
        ]);
    }
}
