<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\Persistence\postgres;

use App\Application\Ports\Outbound\ObligacionRepositoryPort;
use App\Domain\ObligacionProveedor;
use PDO;

final class PdoObligacionRepository implements ObligacionRepositoryPort
{
    public function __construct(private readonly PDO $pdo) {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS obligaciones_proveedor (
              id UUID PRIMARY KEY,
              orden_id TEXT NOT NULL,
              proveedor TEXT NOT NULL,
              monto NUMERIC(10,2) NOT NULL,
              detalle TEXT NOT NULL,
              estado TEXT NOT NULL,
              creada_en TIMESTAMPTZ NOT NULL,
              UNIQUE (orden_id, proveedor)
            );
        SQL);
    }

    public function guardarSiNoExiste(ObligacionProveedor $o): ObligacionProveedor
    {
        $this->pdo->prepare('INSERT INTO obligaciones_proveedor (id, orden_id, proveedor, monto, detalle, estado, creada_en)
            VALUES (:id,:orden,:prov,:monto,:detalle,:estado,:creada) ON CONFLICT (orden_id, proveedor) DO NOTHING')
            ->execute([':id' => $o->id, ':orden' => $o->ordenId, ':prov' => $o->proveedor, ':monto' => $o->monto,
                ':detalle' => json_encode($o->detalle, JSON_UNESCAPED_UNICODE), ':estado' => $o->estado,
                ':creada' => $o->creadaEn->format(DATE_ATOM)]);
        $s = $this->pdo->prepare('SELECT * FROM obligaciones_proveedor WHERE orden_id = :orden AND proveedor = :prov');
        $s->execute([':orden' => $o->ordenId, ':prov' => $o->proveedor]);
        return self::desdeFila($s->fetch(PDO::FETCH_ASSOC));
    }

    public function todas(): array
    {
        $filas = $this->pdo->query('SELECT * FROM obligaciones_proveedor ORDER BY creada_en DESC')->fetchAll(PDO::FETCH_ASSOC);
        return array_map([self::class, 'desdeFila'], $filas);
    }

    private static function desdeFila(array $r): ObligacionProveedor
    {
        return new ObligacionProveedor($r['id'], $r['orden_id'], $r['proveedor'], (float)$r['monto'],
            json_decode($r['detalle'], true) ?: [], $r['estado'], new \DateTimeImmutable($r['creada_en']));
    }
}
