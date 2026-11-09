<?php
declare(strict_types=1);

namespace Tests\Application;

use App\Application\Dto\{CobroQrResult, DevolucionResult, FacturaResult, VerificacionPagoResult};
use App\Application\Ports\Outbound\{EventBusPort, IdGeneratorPort, PagoRepositoryPort, PaymentGatewayPort, TicketRepositoryPort};
use App\Application\Services\CobrarRacionService;
use App\Domain\{Pago, Ticket};
use ArrayObject;
use PHPUnit\Framework\TestCase;

/** Paso 3 de la Saga 1 con adaptadores falsos: ids deterministas, pasarela y bus en memoria. */
final class CobrarRacionTest extends TestCase
{
    private ArrayObject $pagos;
    private ArrayObject $tickets;
    private ArrayObject $eventos;

    protected function setUp(): void
    {
        $this->pagos = new ArrayObject();
        $this->tickets = new ArrayObject();
        $this->eventos = new ArrayObject();
    }

    private function servicio(?CobroQrResult $respuestaQr = null): CobrarRacionService
    {
        $pagos = new class($this->pagos) implements PagoRepositoryPort {
            public function __construct(private ArrayObject $datos) {}
            public function save(Pago $pago): void { $this->datos[$pago->id] = $pago; }
            public function findById(string $id): ?Pago { return $this->datos[$id] ?? null; }
            public function todos(): array { return array_values($this->datos->getArrayCopy()); }
            public function delEstudiante(string $estudianteId): array { return []; }
        };
        $tickets = new class($this->tickets) implements TicketRepositoryPort {
            public function __construct(private ArrayObject $datos) {}
            public function save(Ticket $t): void { $this->datos[$t->id] = $t; }
            public function findById(string $id): ?Ticket { return $this->datos[$id] ?? null; }
        };
        $gateway = new class($respuestaQr ?? CobroQrResult::exito('cs_123', 'https://pagar', 'data:image/png;base64,QR')) implements PaymentGatewayPort {
            public function __construct(private CobroQrResult $r) {}
            public function cobrarQr(float $monto, string $referencia): CobroQrResult { return $this->r; }
            public function devolver(string $referencia): DevolucionResult { return DevolucionResult::sinCobro(); }
            public function verificarPago(string $referencia): VerificacionPagoResult { return new VerificacionPagoResult(true, 'paid'); }
            public function facturaUrl(string $referencia): FacturaResult { return FacturaResult::noDisponible('no'); }
        };
        $bus = new class($this->eventos) implements EventBusPort {
            public function __construct(private ArrayObject $eventos) {}
            public function publish(string $topic, array $payload): void { $this->eventos->append([$topic, $payload]); }
        };
        $ids = new class implements IdGeneratorPort {
            private int $n = 0;
            public function nuevoId(): string { return 'id-' . ++$this->n; }
        };
        return new CobrarRacionService($pagos, $tickets, $gateway, $bus, $ids);
    }

    public function test_beca_cobra_cero_y_emite_ticket_con_ids_del_puerto(): void
    {
        $r = $this->servicio()->execute('r1', 'e1', 15.0, Pago::METODO_BECA, 'saga-1');
        $this->assertSame('id-1', $r->pagoId);
        $this->assertSame('id-2', $r->ticketId);
        $this->assertSame(Pago::ESTADO_COBRADO, $r->estado);
        $this->assertNull($r->pasarela);
        $this->assertSame(0.0, $this->pagos['id-1']->monto);
        $this->assertSame(0.0, $this->tickets['id-2']->monto);
        $this->assertSame('dominio-pagos', $this->eventos[0][0]);
        $this->assertSame('PagoRealizadoEvent', $this->eventos[0][1]['type']);
        $this->assertSame(0.0, $this->eventos[0][1]['monto']);
    }

    public function test_qr_guarda_la_referencia_de_la_pasarela_para_poder_devolver(): void
    {
        $this->servicio()->execute('r1', 'e1', 15.0, Pago::METODO_QR);
        $this->assertSame('cs_123', $this->pagos['id-1']->referenciaPasarela);
    }

    public function test_efectivo_no_tiene_referencia_de_pasarela(): void
    {
        $this->servicio()->execute('r1', 'e1', 15.0, Pago::METODO_EFECTIVO);
        $this->assertNull($this->pagos['id-1']->referenciaPasarela);
    }

    public function test_qr_devuelve_los_datos_de_la_pasarela(): void
    {
        $r = $this->servicio()->execute('r1', 'e1', 15.0, Pago::METODO_QR);
        $this->assertNotNull($r->pasarela);
        $this->assertSame('https://pagar', $r->pasarela->paymentUrl);
        $this->assertSame('data:image/png;base64,QR', $r->pasarela->qrDataUri);
        $this->assertSame('cs_123', $r->pasarela->referencia);
        $this->assertSame(15.0, $this->pagos['id-1']->monto);
    }

    public function test_qr_fallido_marca_el_pago_y_lanza(): void
    {
        try {
            $this->servicio(CobroQrResult::fallo('tarjeta rechazada'))->execute('r1', 'e1', 15.0, Pago::METODO_QR);
            $this->fail('Debía lanzar');
        } catch (\RuntimeException $e) {
            $this->assertSame('Cobro QR fallido: tarjeta rechazada', $e->getMessage());
        }
        $this->assertSame(Pago::ESTADO_FALLIDO, $this->pagos['id-1']->estado);
        $this->assertCount(0, $this->tickets);
        $this->assertCount(0, $this->eventos);
    }
}
