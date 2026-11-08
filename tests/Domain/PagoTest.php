<?php
declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Pago;
use PHPUnit\Framework\TestCase;

final class PagoTest extends TestCase
{
    private function nuevo(string $metodo = Pago::METODO_QR): Pago
    {
        return new Pago('p1', 'r1', 'e1', 10.0, $metodo, Pago::ESTADO_PENDIENTE, new \DateTimeImmutable());
    }

    public function test_cobrar_cambia_estado_y_asigna_ticket(): void
    {
        $p = $this->nuevo();
        $p->cobrar('t1');
        $this->assertSame(Pago::ESTADO_COBRADO, $p->estado);
        $this->assertSame('t1', $p->ticketId);
    }

    public function test_no_cobra_dos_veces(): void
    {
        $p = $this->nuevo(); $p->cobrar('t1');
        $this->expectException(\DomainException::class);
        $p->cobrar('t2');
    }

    public function test_devolucion_solo_tras_cobro(): void
    {
        $p = $this->nuevo();
        $this->expectException(\DomainException::class);
        $p->devolver();
    }

    public function test_metodo_invalido(): void
    {
        $this->expectException(\DomainException::class);
        new Pago('p', 'r', 'e', 1.0, 'cripto', Pago::ESTADO_PENDIENTE, new \DateTimeImmutable());
    }
}
