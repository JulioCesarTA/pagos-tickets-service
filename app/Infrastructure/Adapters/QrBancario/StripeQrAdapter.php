<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\QrBancario;

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

    public function cobrarQr(float $monto, string $referencia): array
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
            return [
                'ok' => true,
                'referencia' => $link->id,
                'paymentUrl' => $link->url,
                'qrDataUri' => $qrDataUri,
            ];
        } catch (\Throwable $e) {
            $this->log->error("Stripe falló: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica contra Stripe si el Payment Link fue pagado.
     * Devuelve ['paid'=>bool,'status'=>string,'amount'=>int].
     * Un Payment Link puede generar múltiples checkout sessions:
     * si al menos una está 'paid' consideramos la referencia pagada.
     */
    public function verificarPago(string $sessionId): array
    {
        try {
            $s = $this->stripe->checkout->sessions->retrieve($sessionId);
            $paid = ($s->payment_status ?? '') === 'paid';
            return [
                'paid' => $paid,
                'status' => $s->payment_status ?? 'unknown',
                'amount' => $s->amount_total ?? 0,
                'sessionId' => $s->id,
                'currency' => $s->currency ?? 'usd',
            ];
        } catch (\Throwable $e) {
            $this->log->error("Stripe verificar falló: " . $e->getMessage());
            return ['paid' => false, 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Factura hospedada de Stripe (si la sesión generó invoice) o, en su defecto,
     * el recibo del cargo. Solo existe cuando la sesión está pagada.
     */
    public function facturaUrl(string $referencia): array
    {
        try {
            $s = $this->stripe->checkout->sessions->retrieve($referencia, [
                'expand' => ['invoice', 'payment_intent.latest_charge'],
            ]);
            if (($s->payment_status ?? '') !== 'paid') {
                return ['error' => 'El pago todavía no está acreditado'];
            }
            if (!empty($s->invoice->hosted_invoice_url)) {
                return ['url' => $s->invoice->hosted_invoice_url, 'tipo' => 'factura', 'pdf' => $s->invoice->invoice_pdf ?? null];
            }
            $receipt = $s->payment_intent->latest_charge->receipt_url ?? null;
            if ($receipt) return ['url' => $receipt, 'tipo' => 'recibo'];
            return ['error' => 'Stripe aún no emitió la factura'];
        } catch (\Throwable $e) {
            $this->log->error("Stripe factura falló: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function devolver(string $referencia): bool
    {
        try {
            // En producción harías refund del PaymentIntent asociado.
            // Para demo: desactivar el payment link.
            $this->stripe->checkout->sessions->expire($referencia);
            return true;
        } catch (\Throwable $e) {
            $this->log->error("Stripe refund falló: " . $e->getMessage());
            return false;
        }
    }
}
