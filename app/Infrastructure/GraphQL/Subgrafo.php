<?php
declare(strict_types=1);

namespace App\Infrastructure\GraphQL;

use App\Application\Ports\Inbound\ConsultarPagosUseCase;
use App\Domain\Pago;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;

/**
 * Subgrafo de Apollo Federation de pagos: un adaptador de entrada más del hexágono (solo usa puertos de entrada).
 * Lo expone POST /graphql. El gateway compone este esquema con el de menú y el de comensales (SPEC §4).
 * Extiende la entidad Estudiante, que es de comensales: así `estudiante { nombre pagos { monto } }` cruza dos servicios.
 */
final class Subgrafo
{
    /** Lo que el gateway lee con `_service { sdl }` para componer el supergrafo. */
    public const SDL = <<<'GRAPHQL'
    extend schema @link(url: "https://specs.apollo.dev/federation/v2.3", import: ["@key"])

    type Pago @key(fields: "id") {
      id: ID!
      reservaId: String!
      estudianteId: String!
      monto: Float!
      metodo: String!
      estado: String!
      ticketId: String
      fecha: String!
    }

    type Estudiante @key(fields: "id") {
      id: ID!
      pagos: [Pago!]!
    }

    type Query {
      pagos(estudianteId: String): [Pago!]!
      pago(id: ID!): Pago
    }
    GRAPHQL;

    /** El mismo esquema más lo que Federation exige para ejecutarse (_service y _entities). */
    private const EJECUTABLE = <<<'GRAPHQL'
    directive @key(fields: String!, resolvable: Boolean = true) repeatable on OBJECT

    scalar _Any

    type _Service { sdl: String }

    union _Entity = Pago | Estudiante

    type Pago @key(fields: "id") {
      id: ID!
      reservaId: String!
      estudianteId: String!
      monto: Float!
      metodo: String!
      estado: String!
      ticketId: String
      fecha: String!
    }

    type Estudiante @key(fields: "id") {
      id: ID!
      pagos: [Pago!]!
    }

    type Query {
      pagos(estudianteId: String): [Pago!]!
      pago(id: ID!): Pago
      _service: _Service!
      _entities(representations: [_Any!]!): [_Entity]!
    }
    GRAPHQL;

    private ?Schema $esquema = null;

    public function __construct(private readonly ConsultarPagosUseCase $consultar) {}

    private function esquema(): Schema
    {
        return $this->esquema ??= BuildSchema::build(self::EJECUTABLE, static function (array $config): array {
            if ($config['name'] === '_Any') {
                $config['serialize'] = static fn ($v) => $v;
                $config['parseValue'] = static fn ($v) => $v;
                $config['parseLiteral'] = static fn (Node $n) => AST::valueFromASTUntyped($n);
            }
            if ($config['name'] === '_Entity') {
                $config['resolveType'] = static fn (array $v) => $v['__typename'];
            }
            return $config;
        });
    }

    private static function fila(Pago $p): array
    {
        return ['__typename' => 'Pago', 'id' => $p->id, 'reservaId' => $p->reservaId, 'estudianteId' => $p->estudianteId,
            'monto' => $p->monto, 'metodo' => $p->metodo, 'estado' => $p->estado, 'ticketId' => $p->ticketId,
            'fecha' => $p->createdAt->format(DATE_ATOM)];
    }

    private function pagosDe(?string $estudianteId): array
    {
        return array_map([self::class, 'fila'], $this->consultar->listar($estudianteId));
    }

    private function entidad(array $r): ?array
    {
        if (($r['__typename'] ?? '') === 'Pago') {
            $p = $this->consultar->ver((string)($r['id'] ?? ''));
            return $p ? self::fila($p) : null;
        }
        if (($r['__typename'] ?? '') === 'Estudiante') {
            $id = (string)($r['id'] ?? '');       // cada pago guarda el id interno del estudiante, no su registro
            return ['__typename' => 'Estudiante', 'id' => $id, 'pagos' => fn () => $this->pagosDe($id)];
        }
        return null;
    }

    public function ejecutar(string $consulta, ?array $variables = null, ?string $operacion = null): array
    {
        $raiz = [
            'pagos' => fn ($_, array $a) => $this->pagosDe($a['estudianteId'] ?? null),
            'pago' => function ($_, array $a) {
                $p = $this->consultar->ver((string)$a['id']);
                return $p ? self::fila($p) : null;
            },
            '_service' => ['sdl' => self::SDL],
            '_entities' => fn ($_, array $a) => array_map(fn (array $r) => $this->entidad($r), $a['representations']),
        ];
        return GraphQL::executeQuery($this->esquema(), $consulta, $raiz, null, $variables, $operacion)->toArray(DebugFlag::NONE);
    }
}
