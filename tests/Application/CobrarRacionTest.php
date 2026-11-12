<?php
declare(strict_types=1);

namespace Tests\Application;

use App\Application\Dto\{CobroQrResult, DevolucionResult, FacturaResult, VerificacionPagoResult};
use App\Application\Ports\Outbound\{EventBusPort, IdGeneratorPort, PagoRepositoryPort, PaymentGatewayPort, TicketRepositoryPort};
use App\Application\Services\{CobrarRacionService, CompletarCobro, VerificarPagoService};
use App\Domain\{Pago, Ticket};
use ArrayObject;
use PHPUnit\Framework\TestCase;

/**
 * Paso 3 de la Saga 1 y verificación del QR, con adaptadores falsos (ids deterministas, pasarela y bus en memoria).
 * Regla central: un pago por QR solo se cobra cuando la pasarela confirma que se pagó; beca y efectivo, en el acto.
 */
final class CobrarRacionTest extends TestCase
{
    private ArrayObject $pagos;
    private ArrayObject $tickets;
    private ArrayObject $eventos;
    private object $gateway;
    private CompletarCobro $completar;
    private PagoRepositoryPort $repo;
    private IdGeneratorPort $ids;

    protected function setUp(): void
    {
        $this->pagos = new ArrayObject();
        $this->tickets = new ArrayObject();
        $this->eventos = new ArrayObject();
        $this->repo = new class($this->pagos) implements PagoRepositoryPort {
            public function __construct(private ArrayObject $datos) {}
            public function save(Pago $pago): void { $this->datos[$pago->id] = $pago; }
            public function findById(string $id): ?Pago { return $this->datos[$id] ?? null; }
            public function findByReferenciaPasarela(string $referencia): ?Pago
            {
                foreach ($this->datos as $p) if ($p->referenciaPasarela === $referencia) return $p;
                return null;
            }
            public function todos(): array { return array_values($this->datos->getArrayCopy()); }
            public function delEstudiante(string $estudianteId): array { return []; }
        };
        $tickets = new class($this->tickets) implements TicketRepositoryPort {
            public function __construct(private ArrayObject $datos) {}
            public function save(Ticket $t): void { $this->datos[$t->id] = $t; }
            public function findById(string $id): ?Ticket { return $this->datos[$id] ?? null; }
        };
        $bus = new class($this->eventos) implements EventBusPort {
            public function __construct(private ArrayObject $eventos) {}
            public function publish(string $topic, array $payload): void { $this->eventos->append([$topic, $payload]); }
        };
        $this->ids = new class implements IdGeneratorPort {
            private int $n = 0;
            public function nuevoId(): string { return 'id-' . ++$this->n; }
        };
        $this->gateway = new class implements PaymentGatewayPort {
            public CobroQrResult $respuestaQr;
            public bool $pagado = false;
            public function __construct() { $this->respuestaQr = CobroQrResult::exito('cs_123', 'https://pagar', 'data:image/png;base64,QR'); }
            public function cobrarQr(float $monto, string $referencia): CobroQrResult { return $this->respuestaQr; }
            public function devolver(string $referencia): DevolucionResult { return DevolucionResult::sinCobro(); }
            public function verificarPago(string $referencia): VerificacionPagoResult
            {
                return new VerificacionPagoResult($this->pagado, $this->pagado ? 'paid' : 'unpaid');
            }
            public function facturaUrl(string $referencia): FacturaResult { return FacturaResult::noDisponible('no'); }
        };
        $this->completar = new CompletarCobro($this->repo, $tickets, $bus, $this->ids);
    }

    private function cobrar(): CobrarRacionService
    {
        return new CobrarRacionService($this->repo, $this->gateway, $this->ids, $this->completar);
    }

    private function verificar(): VerificarPagoService
    {
        return new VerificarPagoService($this->gateway, $this->repo, $this->completar);
    }

    private function tipos(): array
    {
        return array_map(fn ($e) => $e[1]['type'], $this->eventos->getArrayCopy());
    }

    public function test_beca_cobra_cero_y_emite_ticket_con_ids_del_puerto(): void
    {
        $r = $this->cobrar()->execute('r1', 'e1', 15.0, Pago::METODO_BECA, 'saga-1');
        $this->assertSame('id-1', $r->pagoId);
        $this->assertSame('id-2', $r->ticketId);
        $this->assertSame(Pago::ESTADO_COBRADO, $r->estado);
        $this->assertNull($r->pasarela);
        $this->assertSame(0.0, $this->pagos['id-1']->monto);
        $this->assertSame(0.0, $this->tickets['id-2']->monto);
        $this->assertSame('dominio-pagos', $this->eventos[0][0]);
        $this->assertSame('PagoRealizadoEvent', $this->eventos[0][1]['type']);
        $this->assertSame('saga-1', $this->eventos[0][1]['sagaId']);
        $this->assertSame(0.0, $this->eventos[0][1]['monto']);
    }

    public function test_efectivo_se_cobra_en_el_acto_y_no_tiene_referencia_de_pasarela(): void
    {
        $r = $this->cobrar()->execute('r1', 'e1', 15.0, Pago::METODO_EFECTIVO);
        $this->assertSame(Pago::ESTADO_COBRADO, $r->estado);
        $this->assertNull($this->pagos['id-1']->referenciaPasarela);
        $this->assertSame(['PagoRealizadoEvent'], $this->tipos());
    }

    public function test_qr_queda_pendiente_sin_ticket_ni_evento_hasta_que_se_pague(): void
    {
        $r = $this->cobrar()->execute('r1', 'e1', 15.0, Pago::METODO_QR);
        $this->assertSame(Pago::ESTADO_PENDIENTE, $r->estado, 'generar el QR no es cobrar');
        $this->assertNull($r->ticketId);
        $this->assertCount(0, $this->tickets);
        $this->assertCount(0, $this->eventos, 'no se publica PagoRealizado antes de que se pague');
        $this->assertSame('cs_123', $this->pagos['id-1']->referenciaPasarela);
    }

    public function test_qr_devuelve_los_datos_de_la_pasarela(): void
    {
        $r = $this->cobrar()->execute('r1', 'e1', 15.0, Pago::METODO_QR);
        $this->assertNotNull($r->pasarela);
        $this->assertSame('https://pagar', $r->pasarela->paymentUrl);
        $this->assertSame('data:image/png;base64,QR', $r->pasarela->qrDataUri);
        $this->assertSame('cs_123', $r->pasarela->referencia);
        $this->assertSame(15.0, $this->pagos['id-1']->monto);
    }

    public function test_qr_fallido_marca_el_pago_y_lanza(): void
    {
        $this->gateway->respuestaQr = CobroQrResult::fallo('tarjeta rechazada');
        try {
            $this->cobrar()->execute('r1', 'e1', 15.0, Pago::METODO_QR);
            $this->fail('Debía lanzar');
        } catch (\RuntimeException $e) {
            $this->assertSame('Cobro QR fallido: tarjeta rechazada', $e->getMessage());
        }
        $this->assertSame(Pago::ESTADO_FALLIDO, $this->pagos['id-1']->estado);
        $this->assertCount(0, $this->tickets);
        $this->assertCount(0, $this->eventos);
    }

    public function test_verificar_un_qr_no_pagado_lo_deja_pendiente(): void
    {
        $this->cobrar()->execute('r1', 'e1', 15.0, Pago::METODO_QR);
        $this->assertFalse($this->verificar()->execute('cs_123')->paid);
        $this->assertSame(Pago::ESTADO_PENDIENTE, $this->pagos['id-1']->estado);
        $this->assertCount(0, $this->eventos);
    }

    public function test_verificar_un_qr_pagado_lo_cobra_una_sola_vez(): void
    {
        $this->cobrar()->execute('r1', 'e1', 15.0, Pago::METODO_QR);
        $this->gateway->pagado = true;
        $this->verificar()->execute('cs_123');
        $this->verificar()->execute('cs_123');               // el estudiante aprieta «Verificar pago» dos veces

        $this->assertSame(Pago::ESTADO_COBRADO, $this->pagos['id-1']->estado);
        $this->assertCount(1, $this->tickets);
        $this->assertSame(['PagoRealizadoEvent'], $this->tipos());
        $this->assertSame(15.0, $this->eventos[0][1]['monto']);
    }

    public function test_qr_abandonado_no_suma_ingresos(): void
    {
        // El estudiante genera el QR tres veces y paga solo la última: solo ese pago queda cobrado.
        foreach (['cs_a', 'cs_b', 'cs_c'] as $ref) {
            $this->gateway->respuestaQr = CobroQrResult::exito($ref, "https://pagar/$ref", 'data:QR');
            $this->cobrar()->execute('r1', 'e1', 12.0, Pago::METODO_QR);
        }
        $this->gateway->pagado = true;
        $this->verificar()->execute('cs_c');

        $cobrados = array_filter($this->pagos->getArrayCopy(), fn (Pago $p) => $p->estado === Pago::ESTADO_COBRADO);
        $this->assertCount(1, $cobrados);
        $this->assertSame(12.0, array_sum(array_map(fn (Pago $p) => $p->monto, $cobrados)));
    }
}
