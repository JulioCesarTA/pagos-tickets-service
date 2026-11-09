<?php
declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Application\Ports\Inbound\CobrarRacionUseCase;
use App\Application\Ports\Inbound\ConsultarPagosUseCase;
use App\Infrastructure\Presentation\PagosJsonMapper;

/**
 * Servidor MCP (Model Context Protocol): JSON-RPC 2.0 sin estado. Es un adaptador de entrada más del hexágono:
 * traduce cada herramienta a un puerto de entrada (caso de uso). Lo expone POST /mcp.
 * Herramientas (SPEC §3.3): cobrar_racion y consultar_estado_pago.
 */
final class McpServer
{
    private const VERSION = '2025-06-18';
    private const VERSIONES = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct(
        private readonly CobrarRacionUseCase $cobrar,
        private readonly ConsultarPagosUseCase $consultar,
    ) {}

    private function herramientas(): array
    {
        return [
            'cobrar_racion' => [
                'description' => 'Cobra una ración de una reserva: beca, QR bancario o efectivo. Emite el ticket (paso 3 de la Saga 1).',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'reservaId' => ['type' => 'string'], 'estudianteId' => ['type' => 'string'],
                    'monto' => ['type' => 'number', 'minimum' => 0],
                    'metodo' => ['type' => 'string', 'enum' => ['beca', 'qr_bancario', 'efectivo']],
                ], 'required' => ['reservaId', 'estudianteId', 'monto', 'metodo']],
                'accion' => fn (array $a) => PagosJsonMapper::cobro($this->cobrar->execute(
                    self::texto($a, 'reservaId'), self::texto($a, 'estudianteId'), (float)self::texto($a, 'monto'), self::texto($a, 'metodo'))),
            ],
            'consultar_estado_pago' => [
                'description' => 'Estado de un pago (pendiente, cobrado, devuelto o fallido), con su método, monto y ticket.',
                'inputSchema' => ['type' => 'object', 'properties' => ['pagoId' => ['type' => 'string']], 'required' => ['pagoId']],
                'accion' => function (array $a) {
                    $p = $this->consultar->ver(self::texto($a, 'pagoId'));
                    if (!$p) throw new \DomainException('El pago no existe');
                    return ['id' => $p->id, 'reservaId' => $p->reservaId, 'monto' => $p->monto, 'metodo' => $p->metodo,
                        'estado' => $p->estado, 'ticketId' => $p->ticketId];
                },
            ],
        ];
    }

    private static function texto(array $a, string $campo): string
    {
        $v = $a[$campo] ?? null;
        if ($v === null || trim((string)$v) === '') throw new \InvalidArgumentException("Falta el argumento «{$campo}»");
        return (string)$v;
    }

    /** Procesa un mensaje JSON-RPC. Devuelve null si era una notificación (se responde 202 sin cuerpo). */
    public function procesar(array $msg): ?array
    {
        if (!array_key_exists('id', $msg) || $msg['id'] === null) return null;
        $id = $msg['id'];
        $params = is_array($msg['params'] ?? null) ? $msg['params'] : [];
        switch ($msg['method'] ?? '') {
            case 'initialize':
                $v = $params['protocolVersion'] ?? '';
                return self::ok($id, [
                    'protocolVersion' => in_array($v, self::VERSIONES, true) ? $v : self::VERSION,
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    'serverInfo' => ['name' => 'pagos-tickets-service', 'version' => '1.0.0'],
                ]);
            case 'ping':
                return self::ok($id, new \stdClass());
            case 'tools/list':
                $lista = [];
                foreach ($this->herramientas() as $nombre => $h) {
                    $lista[] = ['name' => $nombre, 'description' => $h['description'], 'inputSchema' => $h['inputSchema']];
                }
                return self::ok($id, ['tools' => $lista]);
            case 'tools/call':
                $h = $this->herramientas()[$params['name'] ?? ''] ?? null;
                if (!$h) return self::error($id, -32602, 'Herramienta desconocida: ' . ($params['name'] ?? ''));
                try {
                    $r = ($h['accion'])(is_array($params['arguments'] ?? null) ? $params['arguments'] : []);
                    return self::ok($id, ['content' => [['type' => 'text', 'text' => json_encode($r)]],
                        'structuredContent' => ['resultado' => $r], 'isError' => false]);
                } catch (\Throwable $e) {       // error al ejecutar: MCP lo devuelve como resultado con isError
                    return self::ok($id, ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true]);
                }
            default:
                return self::error($id, -32601, 'Método no soportado: ' . ($msg['method'] ?? ''));
        }
    }

    private static function ok(mixed $id, mixed $result): array { return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]; }

    private static function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
