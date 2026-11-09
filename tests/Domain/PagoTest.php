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

    public function test_pago_nuevo_con_beca_no_paga(): void
    {
        $p = Pago::nuevo('p1', 'r1', 'e1', 12.5, Pago::METODO_BECA, new \DateTimeImmutable());
        $this->assertSame(0.0, $p->monto);
        $this->assertSame(Pago::ESTADO_PENDIENTE, $p->estado);
        $this->assertNull($p->ticketId);
    }

    public function test_pago_nuevo_sin_beca_conserva_el_monto(): void
    {
        $this->assertSame(12.5, Pago::nuevo('p1', 'r1', 'e1', 12.5, Pago::METODO_QR, new \DateTimeImmutable())->monto);
        $this->assertSame(7.0, Pago::nuevo('p2', 'r2', 'e2', 7.0, Pago::METODO_EFECTIVO, new \DateTimeImmutable())->monto);
    }

    public function test_pago_nuevo_valida_metodo_y_monto(): void
    {
        $this->expectException(\DomainException::class);
        Pago::nuevo('p1', 'r1', 'e1', -1.0, Pago::METODO_EFECTIVO, new \DateTimeImmutable());
    }

    public function test_metodo_invalido(): void
    {
        $this->expectException(\DomainException::class);
        new Pago('p', 'r', 'e', 1.0, 'cripto', Pago::ESTADO_PENDIENTE, new \DateTimeImmutable());
    }

    public function test_pago_qr_guarda_la_referencia_de_la_pasarela(): void
    {
        $p = Pago::nuevo('p1', 'r1', 'e1', 12.0, Pago::METODO_QR, new \DateTimeImmutable());
        $this->assertTrue($p->requiereDevolucionEnPasarela());
        $p->asociarReferenciaPasarela('cs_test_123');
        $this->assertSame('cs_test_123', $p->referenciaPasarela);
    }

    public function test_un_pago_en_efectivo_no_tiene_referencia_de_pasarela(): void
    {
        $p = Pago::nuevo('p1', 'r1', 'e1', 12.0, Pago::METODO_EFECTIVO, new \DateTimeImmutable());
        $this->assertFalse($p->requiereDevolucionEnPasarela());
        $this->expectException(\DomainException::class);
        $p->asociarReferenciaPasarela('cs_test_123');
    }

    public function test_la_referencia_de_pasarela_no_puede_ser_vacia(): void
    {
        $p = Pago::nuevo('p1', 'r1', 'e1', 12.0, Pago::METODO_QR, new \DateTimeImmutable());
        $this->expectException(\DomainException::class);
        $p->asociarReferenciaPasarela('  ');
    }
}
