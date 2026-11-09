<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\QrBancario;

use App\Application\Dto\{CobroQrResult, DevolucionResult, FacturaResult, VerificacionPagoResult};
use App\Application\Ports\Outbound\PaymentGatewayPort;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;

/**
 * Adaptador QR bancario real contra Stripe.
 * Crea un Payment Link (Stripe hosted) y devuelve URL + QR PNG en base64.
 * El estudiante escanea el QR con su app bancaria y Stripe captura el cobro.
 */
final class StripeQrAdapter implements PaymentGatewayPort
{
    private StripeClient $stripe;

    public function __construct(
        string $secretKey,
        private readonly LoggerInterface $log,
    ) {
        $this->stripe = new StripeClient($secretKey);
    }

    public function cobrarQr(float $monto, string $referencia): CobroQrResult
    {
        try {
            // Monto en centavos
            $amount = (int) round($monto * 100);

            $portal = getenv('PORTAL_URL') ?: 'http://localhost:3000/portal';
            $link = $this->stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'client_reference_id' => $referencia,
                'invoice_creation' => ['enabled' => true],
                'metadata' => ['referencia' => $referencia],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $amount,
                        'product_data' => ['name' => 'Ración del comedor universitario'],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $portal . '?pago=ok',
                'cancel_url' => $portal . '?pago=cancelado',
            ]);

            // 4) QR del Payment Link URL
            $qrPng = Builder::create()
                ->writer(new PngWriter())
                ->data($link->url)
                ->size(300)
                ->margin(10)
                ->build();

            $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng->getString());

            $this->log->info("Stripe Payment Link creado: $link->url");
            return CobroQrResult::exito($link->id, $link->url, $qrDataUri);
        } catch (\Throwable $e) {
            $this->log->error("Stripe falló: " . $e->getMessage());
            return CobroQrResult::fallo($e->getMessage());
        }
    }

    /**
     * Verifica contra Stripe si el Payment Link fue pagado.
     * Devuelve paid, status, amount (centavos), sessionId y currency.
     * Un Payment Link puede generar múltiples checkout sessions:
     * si al menos una está 'paid' consideramos la referencia pagada.
     */
    public function verificarPago(string $sessionId): VerificacionPagoResult
    {
        try {
            $s = $this->stripe->checkout->sessions->retrieve($sessionId);
            $paid = ($s->payment_status ?? '') === 'paid';
            return new VerificacionPagoResult(
                paid: $paid,
                status: $s->payment_status ?? 'unknown',
                amount: $s->amount_total ?? 0,
                sessionId: $s->id,
                currency: $s->currency ?? 'usd',
            );
        } catch (\Throwable $e) {
            $this->log->error("Stripe verificar falló: " . $e->getMessage());
            return VerificacionPagoResult::fallo($e->getMessage());
        }
    }

    /**
     * Factura hospedada de Stripe (si la sesión generó invoice) o, en su defecto,
     * el recibo del cargo. Solo existe cuando la sesión está pagada.
     */
    public function facturaUrl(string $referencia): FacturaResult
    {
        try {
            $s = $this->stripe->checkout->sessions->retrieve($referencia, [
                'expand' => ['invoice', 'payment_intent.latest_charge'],
            ]);
            if (($s->payment_status ?? '') !== 'paid') {
                return FacturaResult::noDisponible('El pago todavía no está acreditado');
            }
            if (!empty($s->invoice->hosted_invoice_url)) {
                return FacturaResult::factura($s->invoice->hosted_invoice_url, $s->invoice->invoice_pdf ?? null);
            }
            $receipt = $s->payment_intent->latest_charge->receipt_url ?? null;
            if ($receipt) return FacturaResult::recibo($receipt);
            return FacturaResult::noDisponible('Stripe aún no emitió la factura');
        } catch (\Throwable $e) {
            $this->log->error("Stripe factura falló: " . $e->getMessage());
            return FacturaResult::noDisponible($e->getMessage());
        }
    }

    /**
     * $referencia es el id de la checkout session que devolvió cobrarQr().
     *  - Sesión pagada: reembolso completo del PaymentIntent. La clave de idempotencia hace que un reintento
     *    devuelva el mismo reembolso en vez de crear otro.
     *  - Sesión abierta (no pagada): se expira, así el link ya no se puede pagar.
     *  - Sesión expirada sin pago: no hay nada que devolver.
     */
    public function devolver(string $referencia): DevolucionResult
    {
        try {
            $s = $this->stripe->checkout->sessions->retrieve($referencia);

            if (($s->payment_status ?? '') === 'paid') {
                $paymentIntent = is_string($s->payment_intent) ? $s->payment_intent : ($s->payment_intent->id ?? null);
                if (!$paymentIntent) {
                    return DevolucionResult::fallo("La sesión $referencia está pagada pero no tiene PaymentIntent");
                }
                $reembolso = $this->stripe->refunds->create(
                    ['payment_intent' => $paymentIntent, 'metadata' => ['referencia' => $referencia]],
                    ['idempotency_key' => "devolucion-$referencia"],
                );
                $this->log->info("Stripe reembolso creado: $reembolso->id ($reembolso->status) de $paymentIntent");
                return DevolucionResult::reembolsado($reembolso->id);
            }

            if (($s->status ?? '') === 'open') {
                $this->stripe->checkout->sessions->expire($referencia);
                $this->log->info("Stripe sesión sin pagar anulada: $referencia");
            }
            return DevolucionResult::sinCobro();
        } catch (\Throwable $e) {
            $this->log->error("Stripe devolución falló: " . $e->getMessage());
            return DevolucionResult::fallo($e->getMessage());
        }
    }
}
