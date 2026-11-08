<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\CobrarRacionUseCase;
use App\Application\Ports\Outbound\{PagoRepositoryPort, TicketRepositoryPort, PaymentGatewayPort, EventBusPort};
use App\Domain\{Pago, Ticket};
use Ramsey\Uuid\Uuid;

/**
 * Paso 3 de la Saga 1: aplica beca o cobra, emite ticket.
 */
final class CobrarRacionService implements CobrarRacionUseCase
{
    public function __construct(
        private readonly PagoRepositoryPort $pagos,
        private readonly TicketRepositoryPort $tickets,
        private readonly PaymentGatewayPort $gateway,
        private readonly EventBusPort $bus,
    ) {}

    public function execute(string $reservaId, string $estudianteId, float $monto, string $metodo, ?string $sagaId = null): array
    {
        $pago = new Pago(
            id: Uuid::uuid4()->toString(),
            reservaId: $reservaId,
            estudianteId: $estudianteId,
            monto: $metodo === Pago::METODO_BECA ? 0.0 : $monto,
            metodo: $metodo,
            estado: Pago::ESTADO_PENDIENTE,
            createdAt: new \DateTimeImmutable(),
        );

        // Si es QR bancario, cobra en la pasarela externa (Stripe real o Fake).
        $qrInfo = null;
        if ($metodo === Pago::METODO_QR) {
            $res = $this->gateway->cobrarQr($pago->monto, $pago->id);
            if (!$res['ok']) {
                $pago->fallar();
                $this->pagos->save($pago);
                throw new \RuntimeException('Cobro QR fallido: ' . ($res['error'] ?? 'desconocido'));
            }
            $qrInfo = [
                'paymentUrl' => $res['paymentUrl'] ?? null,
                'qrDataUri' => $res['qrDataUri'] ?? null,
                'gatewayRef' => $res['referencia'] ?? null,
            ];
        }

        $ticket = new Ticket(
            id: Uuid::uuid4()->toString(),
            pagoId: $pago->id,
            reservaId: $reservaId,
            monto: $pago->monto,
            emitidoEn: new \DateTimeImmutable(),
        );

        $pago->cobrar($ticket->id);
        $this->pagos->save($pago);
        $this->tickets->save($ticket);

        $this->bus->publish('dominio-pagos', [
            'type' => 'PagoRealizadoEvent',
            'sagaId' => $sagaId,
            'pagoId' => $pago->id,
            'reservaId' => $reservaId,
            'estudianteId' => $estudianteId,
            'monto' => $pago->monto,
            'metodo' => $metodo,
            'ticketId' => $ticket->id,
        ]);

        return [
            'pagoId' => $pago->id,
            'ticketId' => $ticket->id,
            'estado' => $pago->estado,
            'stripe' => $qrInfo,  // null si no es QR; con URL+QR si sí
        ];
    }
}
