<?php
declare(strict_types=1);

namespace Tests\Application;

use App\Application\Dto\{CobroQrResult, FacturaResult, VerificacionPagoResult};
use App\Application\Ports\Outbound\{PagoRepositoryPort, PaymentGatewayPort};
use App\Application\Services\{ConsultarPagosService, ObtenerFacturaService, VerificarPagoService};
use App\Domain\Pago;
use PHPUnit\Framework\TestCase;

/** Los casos de uso se prueban solos: con adaptadores falsos, sin base de datos ni pasarela real. */
final class ConsultasTest extends TestCase
{
    private function repo(Pago ...$pagos): PagoRepositoryPort
    {
        return new class($pagos) implements PagoRepositoryPort {
            public function __construct(private array $datos) {}
            public function save(Pago $pago): void { $this->datos[] = $pago; }
            public function findById(string $id): ?Pago { foreach ($this->datos as $p) if ($p->id === $id) return $p; return null; }
            public function todos(): array { return $this->datos; }
            public function delEstudiante(string $estudianteId): array { return array_values(array_filter($this->datos, fn ($p) => $p->estudianteId === $estudianteId)); }
        };
    }

    private function pasarela(): PaymentGatewayPort
    {
        return new class implements PaymentGatewayPort {
            public function cobrarQr(float $monto, string $referencia): CobroQrResult { return CobroQrResult::exito($referencia); }
            public function devolver(string $referencia): bool { return true; }
            public function verificarPago(string $referencia): VerificacionPagoResult { return new VerificacionPagoResult($referencia === 'pagada', 'x'); }
            public function facturaUrl(string $referencia): FacturaResult { return $referencia === 'ok' ? FacturaResult::factura('https://f', null) : FacturaResult::noDisponible('sin factura'); }
        };
    }

    public function test_listar_y_ver_pagos(): void
    {
        $p = new Pago('p1', 'r1', 'e1', 10.0, Pago::METODO_QR, Pago::ESTADO_PENDIENTE, new \DateTimeImmutable());
        $svc = new ConsultarPagosService($this->repo($p));
        $this->assertSame([$p], $svc->listar());
        $this->assertSame($p, $svc->ver('p1'));
        $this->assertNull($svc->ver('nada'));
    }

    public function test_listar_filtra_por_estudiante(): void
    {
        $a = new Pago('p1', 'r1', 'ana', 10.0, Pago::METODO_QR, Pago::ESTADO_COBRADO, new \DateTimeImmutable());
        $b = new Pago('p2', 'r2', 'beto', 12.0, Pago::METODO_EFECTIVO, Pago::ESTADO_COBRADO, new \DateTimeImmutable());
        $svc = new ConsultarPagosService($this->repo($a, $b));
        $this->assertSame([$a, $b], $svc->listar());
        $this->assertSame([$a, $b], $svc->listar(''));
        $this->assertSame([$b], $svc->listar('beto'));
        $this->assertSame([], $svc->listar('nadie'));
    }

    public function test_factura_y_verificacion_delegan_en_la_pasarela(): void
    {
        $this->assertSame('factura', (new ObtenerFacturaService($this->pasarela()))->execute('ok')->tipo);
        $sin = (new ObtenerFacturaService($this->pasarela()))->execute('otra');
        $this->assertFalse($sin->disponible());
        $this->assertSame('sin factura', $sin->error);
        $this->assertTrue((new VerificarPagoService($this->pasarela()))->execute('pagada')->paid);
    }
}
