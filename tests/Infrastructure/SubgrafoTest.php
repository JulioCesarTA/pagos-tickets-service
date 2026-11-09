<?php
declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Application\Ports\Inbound\ConsultarPagosUseCase;
use App\Domain\Pago;
use App\Infrastructure\GraphQL\Subgrafo;
use PHPUnit\Framework\TestCase;

/** Lo que el gateway de Apollo le pregunta al subgrafo de pagos, con el caso de uso falso. */
final class SubgrafoTest extends TestCase
{
    private function subgrafo(): Subgrafo
    {
        $consultar = new class implements ConsultarPagosUseCase {
            private function uno(string $id, string $estudiante): Pago
            { return new Pago($id, 'r1', $estudiante, 10.0, Pago::METODO_EFECTIVO, Pago::ESTADO_COBRADO, new \DateTimeImmutable('2026-11-09T10:00:00+00:00'), 't1'); }
            public function listar(?string $estudianteId = null): array
            { return $estudianteId === null ? [$this->uno('p1', '220567'), $this->uno('p2', '999')] : [$this->uno('p1', $estudianteId)]; }
            public function ver(string $pagoId): ?Pago { return $pagoId === 'p1' ? $this->uno('p1', '220567') : null; }
        };
        return new Subgrafo($consultar);
    }

    public function test_publica_su_esquema_para_el_gateway(): void
    {
        $sdl = $this->subgrafo()->ejecutar('{ _service { sdl } }')['data']['_service']['sdl'];
        $this->assertStringContainsString('federation/v2', $sdl);
        $this->assertStringContainsString('type Pago @key', $sdl);
    }

    public function test_lista_y_busca_pagos(): void
    {
        $r = $this->subgrafo()->ejecutar('{ pagos { id monto fecha } pago(id: "p1") { ticketId } nada: pago(id: "x") { id } }');
        $this->assertArrayNotHasKey('errors', $r);
        $this->assertCount(2, $r['data']['pagos']);
        $this->assertStringStartsWith('2026-11-09', $r['data']['pagos'][0]['fecha']);
        $this->assertSame('t1', $r['data']['pago']['ticketId']);
        $this->assertNull($r['data']['nada']);
    }

    public function test_extiende_la_entidad_estudiante_de_comensales(): void
    {
        $r = $this->subgrafo()->ejecutar(
            'query($r: [_Any!]!) { _entities(representations: $r) { ... on Estudiante { id pagos { id estudianteId } } ... on Pago { metodo } } }',
            ['r' => [['__typename' => 'Estudiante', 'id' => 'e-1'], ['__typename' => 'Pago', 'id' => 'p1']]],
        );
        $this->assertArrayNotHasKey('errors', $r);
        $this->assertSame('e-1', $r['data']['_entities'][0]['pagos'][0]['estudianteId']);
        $this->assertSame('efectivo', $r['data']['_entities'][1]['metodo']);
    }
}
