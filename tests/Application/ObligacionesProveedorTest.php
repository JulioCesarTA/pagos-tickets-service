<?php
declare(strict_types=1);

namespace Tests\Application;

use App\Application\Ports\Outbound\ObligacionRepositoryPort;
use App\Application\Services\ObligacionesProveedorService;
use App\Domain\ObligacionProveedor;
use PHPUnit\Framework\TestCase;

final class ObligacionesProveedorTest extends TestCase
{
    private function servicio(): ObligacionesProveedorService
    {
        return new ObligacionesProveedorService(new class implements ObligacionRepositoryPort {
            private array $filas = [];
            public function guardarSiNoExiste(ObligacionProveedor $o): ObligacionProveedor
            { return $this->filas[$o->ordenId . '|' . $o->proveedor] ??= $o; }
            public function todas(): array { return array_values($this->filas); }
        });
    }

    public function test_una_orden_repetida_no_duplica_la_obligacion(): void
    {
        $s = $this->servicio();
        $a = $s->registrar('orden-1', 'Agro Yungas', 110.0, ['PAPA: 20 kg']);
        $b = $s->registrar('orden-1', 'Agro Yungas', 110.0, ['PAPA: 20 kg']);
        $this->assertSame($a->id, $b->id);
        $this->assertCount(1, $s->listar());
        $this->assertSame(ObligacionProveedor::ESTADO_PENDIENTE, $a->estado);
    }

    public function test_cada_proveedor_de_la_orden_tiene_su_obligacion(): void
    {
        $s = $this->servicio();
        $s->registrar('orden-1', 'Agro Yungas', 110.0, []);
        $s->registrar('orden-1', 'Unagro', 65.0, []);
        $this->assertCount(2, $s->listar());
    }

    public function test_rechaza_monto_cero_o_sin_proveedor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->servicio()->registrar('orden-1', '', 0, []);
    }
}
