<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\Persistence\postgres;

use App\Application\Ports\Outbound\TicketRepositoryPort;
use App\Domain\Ticket;
use PDO;

final class PdoTicketRepository implements TicketRepositoryPort
{
    public function __construct(private readonly PDO $pdo) {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS tickets (
              id UUID PRIMARY KEY,
              pago_id UUID NOT NULL,
              reserva_id TEXT NOT NULL,
              monto NUMERIC(10,2) NOT NULL,
              emitido_en TIMESTAMPTZ NOT NULL
            );
        SQL);
    }

    public function save(Ticket $t): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO tickets (id, pago_id, reserva_id, monto, emitido_en) VALUES (:id,:p,:r,:m,:e) ON CONFLICT DO NOTHING');
        $stmt->execute([':id' => $t->id, ':p' => $t->pagoId, ':r' => $t->reservaId, ':m' => $t->monto, ':e' => $t->emitidoEn->format(DATE_ATOM)]);
    }

    public function findById(string $id): ?Ticket
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? new Ticket($r['id'], $r['pago_id'], $r['reserva_id'], (float)$r['monto'], new \DateTimeImmutable($r['emitido_en'])) : null;
    }
}
