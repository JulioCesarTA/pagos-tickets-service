<?php
declare(strict_types=1);

namespace Tests\Application;

use App\Application\Dto\{CobroQrResult, DevolucionResult, FacturaResult, VerificacionPagoResult};
use App\Application\Ports\Outbound\{EventBusPort, IdGeneratorPort, PagoRepositoryPort, PaymentGatewayPort, TicketRepositoryPort};
use App\Application\Services\{CompletarCobro, DevolverPagoService};
use App\Domain\{Pago, Ticket};
use ArrayObject;
use PHPUnit\Framework\TestCase;

/** Devolución (compensación de la saga): el dinero se devuelve en la pasarela ANTES de marcar el pago como devuelto. */
final class DevolverPagoTest extends TestCase
{
    private ArrayObject $pagos;
    private ArrayObject $eventos;
    private ArrayObject $llamadas;

    protected function setUp(): void
    {
        $this->pagos = new ArrayObject();
        $this->eventos = new ArrayObject();
        $this->llamadas = new ArrayObject();
    }

    private function servicio(DevolucionResult $respuesta): DevolverPagoService
    {
        $pagos = new class($this->pagos) implements PagoRepositoryPort {
            public function __construct(private ArrayObject $datos) {}
            public function save(Pago $pago): void { $this->datos[$pago->id] = $pago; }
            public function findById(string $id): ?Pago { return $this->datos[$id] ?? null; }
            public function findByReferenciaPasarela(string $referencia): ?Pago { return null; }
            public function todos(): array { return array_values($this->datos->getArrayCopy()); }
            public function delEstudiante(string $estudianteId): array { return []; }
        };
        $gateway = new class($respuesta, $this->llamadas) implements PaymentGatewayPort {
            public function __construct(private DevolucionResult $r, private ArrayObject $llamadas) {}
            public function cobrarQr(float $monto, string $referencia): CobroQrResult { return CobroQrResult::fallo('no se usa'); }
            public function devolver(string $referencia): DevolucionResult { $this->llamadas->append($referencia); return $this->r; }
            public function verificarPago(string $referencia): VerificacionPagoResult { return VerificacionPagoResult::fallo('no se usa'); }
            public function facturaUrl(string $referencia): FacturaResult { return FacturaResult::noDisponible('no'); }
        };
        $bus = new class($this->eventos) implements EventBusPort {
            public function __construct(private ArrayObject $eventos) {}
            public function publish(string $topic, array $payload): void { $this->eventos->append([$topic, $payload]); }
        };
        $tickets = new class implements TicketRepositoryPort {
            public function save(Ticket $t): void {}
            public function findById(string $id): ?Ticket { return null; }
        };
        $ids = new class implements IdGeneratorPort {
            public function nuevoId(): string { return 't-nuevo'; }
        };
        return new DevolverPagoService($pagos, $gateway, $bus, new CompletarCobro($pagos, $tickets, $bus, $ids));
    }

    private function pagoQrPendiente(): Pago
    {
        $p = Pago::nuevo('p1', 'r1', 'e1', 12.0, Pago::METODO_QR, new \DateTimeImmutable());
        $p->asociarReferenciaPasarela('cs_test_pendiente');
        $this->pagos['p1'] = $p;
        return $p;
    }

    private function pagoCobrado(string $metodo, ?string $referencia): Pago
    {
        $p = Pago::nuevo('p1', 'r1', 'e1', 12.0, $metodo, new \DateTimeImmutable());
        if ($referencia !== null) $p->asociarReferenciaPasarela($referencia);
        $p->cobrar('t1');
        $this->pagos['p1'] = $p;
        return $p;
    }

    public function test_qr_pagado_reembolsa_en_la_pasarela_con_la_referencia_del_cobro(): void
    {
        $this->pagoCobrado(Pago::METODO_QR, 'cs_test_abc');
        $this->servicio(DevolucionResult::reembolsado('re_123'))->execute('p1');

        $this->assertSame(['cs_test_abc'], $this->llamadas->getArrayCopy(), 'debe usar la referencia de Stripe, no el id del pago');
        $this->assertSame(Pago::ESTADO_DEVUELTO, $this->pagos['p1']->estado);
        $this->assertSame('PagoDevueltoEvent', $this->eventos[0][1]['type']);
        $this->assertSame('re_123', $this->eventos[0][1]['reembolsoId']);
    }

    public function test_si_la_pasarela_falla_el_pago_no_se_marca_devuelto(): void
    {
        $this->pagoCobrado(Pago::METODO_QR, 'cs_test_abc');
        try {
            $this->servicio(DevolucionResult::fallo('charge_already_refunded'))->execute('p1');
            $this->fail('debía lanzar');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('charge_already_refunded', $e->getMessage());
        }
        $this->assertSame(Pago::ESTADO_COBRADO, $this->pagos['p1']->estado);
        $this->assertCount(0, $this->eventos);
    }

    public function test_qr_sin_referencia_no_se_marca_devuelto_y_pide_devolucion_manual(): void
    {
        $this->pagoCobrado(Pago::METODO_QR, null);
        try {
            $this->servicio(DevolucionResult::reembolsado('re_x'))->execute('p1');
            $this->fail('debía lanzar');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('a mano', $e->getMessage());
        }
        $this->assertCount(0, $this->llamadas, 'no debe llamar a la pasarela con una referencia equivocada');
        $this->assertSame(Pago::ESTADO_COBRADO, $this->pagos['p1']->estado);
    }

    public function test_qr_que_nunca_se_pago_se_anula_y_queda_devuelto(): void
    {
        $this->pagoCobrado(Pago::METODO_QR, 'cs_test_abierta');
        $this->servicio(DevolucionResult::sinCobro())->execute('p1');
        $this->assertSame(Pago::ESTADO_DEVUELTO, $this->pagos['p1']->estado);
        $this->assertNull($this->eventos[0][1]['reembolsoId']);
    }

    public function test_qr_pendiente_que_nadie_pago_se_anula_sin_registrar_ingreso(): void
    {
        $this->pagoQrPendiente();
        $this->servicio(DevolucionResult::sinCobro())->execute('p1');
        $this->assertSame(['cs_test_pendiente'], $this->llamadas->getArrayCopy(), 'debe vencer la sesión en la pasarela');
        $this->assertSame(Pago::ESTADO_FALLIDO, $this->pagos['p1']->estado);
        $this->assertSame(['PagoAnuladoEvent'], array_map(fn ($e) => $e[1]['type'], $this->eventos->getArrayCopy()));
    }

    public function test_qr_pendiente_que_alcanzo_a_pagarse_se_cobra_y_se_devuelve(): void
    {
        $this->pagoQrPendiente();
        $this->servicio(DevolucionResult::reembolsado('re_456'))->execute('p1');
        $this->assertSame(Pago::ESTADO_DEVUELTO, $this->pagos['p1']->estado);
        $this->assertSame(['PagoRealizadoEvent', 'PagoDevueltoEvent'], array_map(fn ($e) => $e[1]['type'], $this->eventos->getArrayCopy()));
        $this->assertSame('re_456', $this->eventos[1][1]['reembolsoId']);
    }

    public function test_efectivo_se_devuelve_sin_tocar_la_pasarela(): void
    {
        $this->pagoCobrado(Pago::METODO_EFECTIVO, null);
        $this->servicio(DevolucionResult::fallo('no debería llamarse'))->execute('p1');
        $this->assertCount(0, $this->llamadas);
        $this->assertSame(Pago::ESTADO_DEVUELTO, $this->pagos['p1']->estado);
    }
}
