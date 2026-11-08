<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\Persistence\postgres;

use App\Application\Ports\Outbound\PagoRepositoryPort;
use App\Domain\Pago;
use PDO;

final class PdoPagoRepository implements PagoRepositoryPort
{
    public function __construct(private readonly PDO $pdo) {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS pagos (
              id UUID PRIMARY KEY,
              reserva_id TEXT NOT NULL,
              estudiante_id TEXT NOT NULL,
              monto NUMERIC(10,2) NOT NULL,
              metodo TEXT NOT NULL,
              estado TEXT NOT NULL,
              ticket_id TEXT NULL,
              created_at TIMESTAMPTZ NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_pagos_reserva ON pagos(reserva_id);
        SQL);
    }

    public function save(Pago $p): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO pagos (id, reserva_id, estudiante_id, monto, metodo, estado, ticket_id, created_at)
            VALUES (:id, :r, :e, :m, :met, :est, :t, :c)
            ON CONFLICT (id) DO UPDATE
            SET estado = EXCLUDED.estado, ticket_id = EXCLUDED.ticket_id
        SQL);
        $stmt->execute([
            ':id' => $p->id, ':r' => $p->reservaId, ':e' => $p->estudianteId,
            ':m' => $p->monto, ':met' => $p->metodo, ':est' => $p->estado,
            ':t' => $p->ticketId, ':c' => $p->createdAt->format(DATE_ATOM),
        ]);
    }

    public function findById(string $id): ?Pago
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pagos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapar($row) : null;
    }

    public function todos(): array
    {
        return array_map([$this, 'mapar'], $this->pdo->query('SELECT * FROM pagos ORDER BY created_at')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByReservaId(string $reservaId): ?Pago
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pagos WHERE reserva_id = :r');
        $stmt->execute([':r' => $reservaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapar($row) : null;
    }

    private function mapar(array $r): Pago
    {
        return new Pago(
            id: $r['id'], reservaId: $r['reserva_id'], estudianteId: $r['estudiante_id'],
            monto: (float)$r['monto'], metodo: $r['metodo'], estado: $r['estado'],
            createdAt: new \DateTimeImmutable($r['created_at']), ticketId: $r['ticket_id'],
        );
    }
}
