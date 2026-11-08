<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\DevolverPagoUseCase;
use App\Application\Ports\Outbound\{PagoRepositoryPort, PaymentGatewayPort, EventBusPort};
use App\Domain\Pago;

final class DevolverPagoService implements DevolverPagoUseCase
{
    public function __construct(
        private readonly PagoRepositoryPort $pagos,
        private readonly PaymentGatewayPort $gateway,
        private readonly EventBusPort $bus,
    ) {}

    public function execute(string $pagoId): void
    {
        $pago = $this->pagos->findById($pagoId);
        if (!$pago) throw new \RuntimeException("Pago no existe: $pagoId");

        if ($pago->metodo === Pago::METODO_QR) {
            $this->gateway->devolver($pago->id);
        }
        $pago->devolver();
        $this->pagos->save($pago);
        $this->bus->publish('dominio-pagos', [
            'type' => 'PagoDevueltoEvent',
            'pagoId' => $pago->id,
            'reservaId' => $pago->reservaId,
        ]);
    }
}
