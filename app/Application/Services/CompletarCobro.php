<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Outbound\{EventBusPort, IdGeneratorPort, PagoRepositoryPort, TicketRepositoryPort};
use App\Domain\{Pago, Ticket};

/**
 * Deja un pago pendiente como COBRADO: emite su ticket, lo guarda y publica PagoRealizadoEvent.
 * Lo usan el cobro inmediato (beca o efectivo) y la verificación de un pago por QR: un pago por QR solo se
 * cobra cuando la pasarela confirma que el estudiante pagó (no al generar el QR).
 */
final class CompletarCobro
{
    public function __construct(
        private readonly PagoRepositoryPort $pagos,
        private readonly TicketRepositoryPort $tickets,
        private readonly EventBusPort $bus,
        private readonly IdGeneratorPort $ids,
    ) {}

    /** @return string el id del ticket emitido */
    public function ejecutar(Pago $pago, ?string $sagaId = null): string
    {
        $ticket = new Ticket(
            id: $this->ids->nuevoId(),
            pagoId: $pago->id,
            reservaId: $pago->reservaId,
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
            'reservaId' => $pago->reservaId,
            'estudianteId' => $pago->estudianteId,
            'monto' => $pago->monto,
            'metodo' => $pago->metodo,
            'ticketId' => $ticket->id,
        ]);
        return $ticket->id;
    }
}
