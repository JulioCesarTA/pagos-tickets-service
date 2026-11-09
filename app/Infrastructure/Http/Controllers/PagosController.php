<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Ports\Inbound\{CobrarRacionUseCase, ConsultarPagosUseCase, DevolverPagoUseCase, ObtenerFacturaUseCase, VerificarPagoUseCase};
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PagosController
{
    public function __construct(
        private readonly CobrarRacionUseCase $cobrar,
        private readonly DevolverPagoUseCase $devolver,
        private readonly ConsultarPagosUseCase $consultar,
        private readonly ObtenerFacturaUseCase $factura,
        private readonly VerificarPagoUseCase $verificarPago,
    ) {}

    public function cobrar(Request $req, Response $res): Response
    {
        $body = (array)$req->getParsedBody();
        try {
            $out = $this->cobrar->execute(
                reservaId: $body['reservaId'],
                estudianteId: $body['estudianteId'],
                monto: (float)($body['monto'] ?? 0),
                metodo: $body['metodo'] ?? 'qr_bancario',
                sagaId: $body['sagaId'] ?? null,
            );
            $res->getBody()->write(json_encode($out));
            return $res->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $res->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    public function devolver(Request $req, Response $res, array $args): Response
    {
        try {
            $this->devolver->execute($args['id']);
            $res->getBody()->write(json_encode(['ok' => true, 'pagoId' => $args['id']]));
        } catch (\Throwable $e) {
            $res->getBody()->write(json_encode(['error' => $e->getMessage()]));
            $res = $res->withStatus(400);
        }
        return $res->withHeader('Content-Type', 'application/json');
    }

    /** GET /pagos → todos los pagos (solo lectura) para el generador de reportes. GET /pagos?estudiante=X → solo los de X (Mis reservas). */
    public function listar(Request $req, Response $res): Response
    {
        $filas = array_map(fn($p) => [
            'id' => $p->id, 'reservaId' => $p->reservaId, 'estudiante' => $p->estudianteId,
            'monto' => $p->monto, 'metodo' => $p->metodo, 'estado' => $p->estado,
            'ticketId' => $p->ticketId, 'fecha' => $p->createdAt->format(DATE_ATOM),
        ], $this->consultar->listar((string)($req->getQueryParams()['estudiante'] ?? '')));
        $res->getBody()->write(json_encode($filas));
        return $res->withHeader('Content-Type', 'application/json');
    }

    public function ver(Request $req, Response $res, array $args): Response
    {
        $p = $this->consultar->ver($args['id']);
        if (!$p) return $res->withStatus(404);
        $res->getBody()->write(json_encode([
            'id' => $p->id, 'reservaId' => $p->reservaId, 'monto' => $p->monto,
            'metodo' => $p->metodo, 'estado' => $p->estado, 'ticketId' => $p->ticketId,
        ]));
        return $res->withHeader('Content-Type', 'application/json');
    }

    /** GET /pagos/recibo/{referencia} → {url, tipo} de la factura de Stripe. */
    public function recibo(Request $req, Response $res, array $args): Response
    {
        $out = $this->factura->execute($args['referencia']);
        $res->getBody()->write(json_encode($out));
        return $res->withStatus(isset($out['url']) ? 200 : 404)->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /pagos/verificar/{referencia}
     * Consulta Stripe el estado real del Payment Link.
     * Devuelve {paid, status, amount?, sessionId?}.
     * Si paid=true marca el pago en BD como PAGADO (idempotente).
     */
    public function verificar(Request $req, Response $res, array $args): Response
    {
        $out = $this->verificarPago->execute($args['referencia']);
        $res->getBody()->write(json_encode($out));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
