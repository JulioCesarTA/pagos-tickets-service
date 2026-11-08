<?php
declare(strict_types=1);

namespace Tests\Application;

use App\Application\Ports\Outbound\{PagoRepositoryPort, PaymentGatewayPort};
use App\Application\Services\{ConsultarPagosService, ObtenerFacturaService, VerificarPagoService};
use App\Domain\Pago;
use PHPUnit\Framework\TestCase;

/** Los casos de uso se prueban solos: con adaptadores falsos, sin base de datos ni Stripe. */
final class ConsultasTest extends TestCase
{
    private function repo(Pago ...$pagos): PagoRepositoryPort
    {
        return new class($pagos) implements PagoRepositoryPort {
            public function __construct(private array $datos) {}
            public function save(Pago $pago): void { $this->datos[] = $pago; }
            public function findById(string $id): ?Pago { foreach ($this->datos as $p) if ($p->id === $id) return $p; return null; }
            public function findByReservaId(string $reservaId): ?Pago { foreach ($this->datos as $p) if ($p->reservaId === $reservaId) return $p; return null; }
            public function todos(): array { return $this->datos; }
        };
    }

    private function pasarela(): PaymentGatewayPort
    {
        return new class implements PaymentGatewayPort {
            public function cobrarQr(float $monto, string $referencia): array { return ['ok' => true]; }
            public function devolver(string $referencia): bool { return true; }
            public function verificarPago(string $referencia): array { return ['paid' => $referencia === 'pagada', 'status' => 'x']; }
            public function facturaUrl(string $referencia): array { return $referencia === 'ok' ? ['url' => 'https://f', 'tipo' => 'factura'] : ['error' => 'sin factura']; }
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

    public function test_factura_y_verificacion_delegan_en_la_pasarela(): void
    {
        $this->assertSame('factura', (new ObtenerFacturaService($this->pasarela()))->execute('ok')['tipo']);
        $this->assertArrayHasKey('error', (new ObtenerFacturaService($this->pasarela()))->execute('otra'));
        $this->assertTrue((new VerificarPagoService($this->pasarela()))->execute('pagada')['paid']);
    }
}
