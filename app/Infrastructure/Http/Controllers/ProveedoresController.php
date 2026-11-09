<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Ports\Inbound\ObligacionesProveedorUseCase;
use App\Domain\ObligacionProveedor;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Obligaciones de pago a proveedores: las registra n8n cuando el administrador aprueba una orden de compra (SPEC §9). */
final class ProveedoresController
{
    public function __construct(private readonly ObligacionesProveedorUseCase $obligaciones) {}

    private static function vista(ObligacionProveedor $o): array
    {
        return ['id' => $o->id, 'ordenId' => $o->ordenId, 'proveedor' => $o->proveedor, 'monto' => $o->monto,
            'detalle' => $o->detalle, 'estado' => $o->estado, 'creadaEn' => $o->creadaEn->format(DATE_ATOM)];
    }

    private static function json(Response $res, mixed $datos, int $estado = 200): Response
    {
        $res->getBody()->write(json_encode($datos, JSON_UNESCAPED_UNICODE));
        return $res->withStatus($estado)->withHeader('Content-Type', 'application/json');
    }

    public function registrar(Request $req, Response $res): Response
    {
        $b = (array)$req->getParsedBody();
        try {
            $o = $this->obligaciones->registrar((string)($b['ordenId'] ?? ''), (string)($b['proveedor'] ?? ''),
                (float)($b['monto'] ?? 0), array_values((array)($b['detalle'] ?? [])));
            return self::json($res, self::vista($o), 201);
        } catch (\InvalidArgumentException $e) {
            return self::json($res, ['error' => $e->getMessage()], 400);
        }
    }

    public function listar(Request $req, Response $res): Response
    {
        return self::json($res, array_map([self::class, 'vista'], $this->obligaciones->listar()));
    }
}
