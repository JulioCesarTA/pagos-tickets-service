<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\{CobroRacionResult, DatosCobroPasarela};
use App\Application\Ports\Inbound\CobrarRacionUseCase;
use App\Application\Ports\Outbound\{EventBusPort, IdGeneratorPort, PagoRepositoryPort, PaymentGatewayPort, TicketRepositoryPort};
use App\Domain\{Pago, Ticket};

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
        private readonly IdGeneratorPort $ids,
    ) {}

    public function execute(string $reservaId, string $estudianteId, float $monto, string $metodo, ?string $sagaId = null): CobroRacionResult
    {
        $pago = Pago::nuevo($this->ids->nuevoId(), $reservaId, $estudianteId, $monto, $metodo, new \DateTimeImmutable());

        // Si es QR bancario, cobra en la pasarela de pagos externa.
        $pasarela = null;
        if ($metodo === Pago::METODO_QR) {
            $res = $this->gateway->cobrarQr($pago->monto, $pago->id);
            if (!$res->ok) {
                $pago->fallar();
                $this->pagos->save($pago);
                throw new \RuntimeException('Cobro QR fallido: ' . ($res->error ?? 'desconocido'));
            }
            $pasarela = new DatosCobroPasarela($res->paymentUrl, $res->qrDataUri, $res->referencia);
        }

        $ticket = new Ticket(
            id: $this->ids->nuevoId(),
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

        // $pasarela es null si no es QR; con URL + QR + referencia si sí.
        return new CobroRacionResult($pago->id, $ticket->id, $pago->estado, $pasarela);
    }
}
