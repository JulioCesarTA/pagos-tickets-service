<?php
declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Application\Dto\{CobroRacionResult, DatosCobroPasarela, FacturaResult, VerificacionPagoResult};
use App\Infrastructure\Adapters\Id\RamseyUuidGenerator;
use App\Infrastructure\Adapters\QrBancario\FakeQrBancarioAdapter;
use App\Infrastructure\Presentation\PagosJsonMapper;
use PHPUnit\Framework\TestCase;

/** El contrato JSON público (lo consumen ia-ml-service y el portal) no cambia con los resultados tipados. */
final class PagosJsonMapperTest extends TestCase
{
    public function test_cobro_sin_pasarela_emite_stripe_null(): void
    {
        $this->assertSame(
            '{"pagoId":"p1","ticketId":"t1","estado":"cobrado","stripe":null}',
            json_encode(PagosJsonMapper::cobro(new CobroRacionResult('p1', 't1', 'cobrado'))),
        );
    }

    public function test_cobro_con_pasarela_emite_el_bloque_stripe_historico(): void
    {
        $r = new CobroRacionResult('p1', 't1', 'cobrado', new DatosCobroPasarela('https://pagar', 'data:x', 'cs_1'));
        $this->assertSame(
            ['pagoId' => 'p1', 'ticketId' => 't1', 'estado' => 'cobrado',
                'stripe' => ['paymentUrl' => 'https://pagar', 'qrDataUri' => 'data:x', 'gatewayRef' => 'cs_1']],
            PagosJsonMapper::cobro($r),
        );
        // La pasarela fake no da URL ni QR: salen como null, igual que antes.
        $fake = new DatosCobroPasarela(null, null, 'QR-p1-abcd');
        $this->assertSame(['paymentUrl' => null, 'qrDataUri' => null, 'gatewayRef' => 'QR-p1-abcd'],
            PagosJsonMapper::cobro(new CobroRacionResult('p1', 't1', 'cobrado', $fake))['stripe']);
    }

    public function test_verificacion_conserva_campos_y_orden(): void
    {
        $this->assertSame(['paid' => true, 'status' => 'paid', 'amount' => 1500, 'sessionId' => 'cs_1', 'currency' => 'usd'],
            PagosJsonMapper::verificacion(new VerificacionPagoResult(true, 'paid', 1500, 'cs_1', 'usd')));
        $this->assertSame(['paid' => false, 'status' => 'error', 'error' => 'boom'],
            PagosJsonMapper::verificacion(VerificacionPagoResult::fallo('boom')));
    }

    public function test_factura_recibo_y_error(): void
    {
        $this->assertSame(['url' => 'https://f', 'tipo' => 'factura', 'pdf' => null], PagosJsonMapper::factura(FacturaResult::factura('https://f', null)));
        $this->assertSame(['url' => 'https://f', 'tipo' => 'factura', 'pdf' => 'https://f.pdf'], PagosJsonMapper::factura(FacturaResult::factura('https://f', 'https://f.pdf')));
        $this->assertSame(['url' => 'https://r', 'tipo' => 'recibo'], PagosJsonMapper::factura(FacturaResult::recibo('https://r')));
        $this->assertSame(['error' => 'no'], PagosJsonMapper::factura(FacturaResult::noDisponible('no')));
    }

    public function test_adaptador_fake_mantiene_sus_respuestas(): void
    {
        $fake = new FakeQrBancarioAdapter();
        $this->assertSame(['paid' => true, 'status' => 'paid', 'sessionId' => 'fake-abcdefgh'],
            PagosJsonMapper::verificacion($fake->verificarPago('abcdefghijk')));
        $this->assertSame(['error' => 'Factura no disponible: Stripe no está configurado'],
            PagosJsonMapper::factura($fake->facturaUrl('x')));
        $cobro = $fake->cobrarQr(10.0, 'p1');
        $this->assertTrue($cobro->ok);
        $this->assertMatchesRegularExpression('/^QR-p1-[0-9a-f]{8}$/', (string)$cobro->referencia);
        $this->assertNull($cobro->paymentUrl);
    }

    public function test_generador_ramsey_da_uuid_v4_distintos(): void
    {
        $g = new RamseyUuidGenerator();
        $a = $g->nuevoId();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a);
        $this->assertNotSame($a, $g->nuevoId());
    }
}
