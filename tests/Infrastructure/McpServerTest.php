<?php
declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Application\Dto\{CobroRacionResult, DatosCobroPasarela};
use App\Application\Ports\Inbound\{CobrarRacionUseCase, ConsultarPagosUseCase};
use App\Domain\Pago;
use App\Infrastructure\Mcp\McpServer;
use PHPUnit\Framework\TestCase;

/** El servidor MCP traduce cada herramienta a un caso de uso; se prueba con casos de uso falsos. */
final class McpServerTest extends TestCase
{
    private function servidor(): McpServer
    {
        $cobrar = new class implements CobrarRacionUseCase {
            public function execute(string $reservaId, string $estudianteId, float $monto, string $metodo, ?string $sagaId = null): CobroRacionResult
            {
                return new CobroRacionResult('p1', 't1', 'cobrado',
                    $metodo === 'qr_bancario' ? new DatosCobroPasarela('https://pagar', 'data:image/png;base64,x', 'cs_1') : null);
            }
        };
        $consultar = new class implements ConsultarPagosUseCase {
            public function listar(?string $estudianteId = null): array { return []; }
            public function ver(string $pagoId): ?Pago
            { return $pagoId === 'p1' ? new Pago('p1', 'r1', 'e1', 10.0, Pago::METODO_EFECTIVO, Pago::ESTADO_COBRADO, new \DateTimeImmutable(), 't1') : null; }
        };
        return new McpServer($cobrar, $consultar);
    }

    private function pedir(string $metodo, array $params = []): array
    {
        return $this->servidor()->procesar(['jsonrpc' => '2.0', 'id' => 7, 'method' => $metodo, 'params' => $params]);
    }

    public function test_initialize_y_notificaciones(): void
    {
        $r = $this->pedir('initialize', ['protocolVersion' => '2025-06-18']);
        $this->assertSame('2025-06-18', $r['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $r['result']['capabilities']);
        $this->assertNull($this->servidor()->procesar(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
    }

    public function test_lista_las_herramientas(): void
    {
        $nombres = array_column($this->pedir('tools/list')['result']['tools'], 'name');
        $this->assertSame(['cobrar_racion', 'consultar_estado_pago'], $nombres);
    }

    public function test_llama_a_los_casos_de_uso(): void
    {
        $r = $this->pedir('tools/call', ['name' => 'cobrar_racion',
            'arguments' => ['reservaId' => 'r1', 'estudianteId' => 'e1', 'monto' => 10, 'metodo' => 'efectivo']]);
        $this->assertFalse($r['result']['isError']);
        $this->assertSame('cobrado', $r['result']['structuredContent']['resultado']['estado']);
        $this->assertSame(['pagoId' => 'p1', 'ticketId' => 't1', 'estado' => 'cobrado', 'stripe' => null], $r['result']['structuredContent']['resultado']);
        $qr = $this->pedir('tools/call', ['name' => 'cobrar_racion',
            'arguments' => ['reservaId' => 'r1', 'estudianteId' => 'e1', 'monto' => 10, 'metodo' => 'qr_bancario']]);
        $this->assertSame(['paymentUrl' => 'https://pagar', 'qrDataUri' => 'data:image/png;base64,x', 'gatewayRef' => 'cs_1'],
            $qr['result']['structuredContent']['resultado']['stripe']);
        $e = $this->pedir('tools/call', ['name' => 'consultar_estado_pago', 'arguments' => ['pagoId' => 'p1']]);
        $this->assertSame('t1', $e['result']['structuredContent']['resultado']['ticketId']);
    }

    public function test_errores(): void
    {
        $this->assertTrue($this->pedir('tools/call', ['name' => 'consultar_estado_pago', 'arguments' => ['pagoId' => 'nada']])['result']['isError']);
        $this->assertTrue($this->pedir('tools/call', ['name' => 'cobrar_racion', 'arguments' => []])['result']['isError']);
        $this->assertSame(-32602, $this->pedir('tools/call', ['name' => 'x'])['error']['code']);
        $this->assertSame(-32601, $this->pedir('resources/list')['error']['code']);
    }
}
