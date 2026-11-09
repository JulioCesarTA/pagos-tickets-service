<?php
declare(strict_types=1);

namespace App\Infrastructure\Presentation;

use App\Application\Dto\{CobroRacionResult, FacturaResult, VerificacionPagoResult};

/**
 * Traduce los resultados tipados de los casos de uso al contrato JSON público (HTTP y MCP).
 * El contrato es anterior al refactor y lo consumen ia-ml-service y el portal: los nombres
 * de campo, su orden y su presencia no deben cambiar.
 */
final class PagosJsonMapper
{
    /** Clave pública histórica del bloque de la pasarela en la respuesta de cobro. */
    public const CLAVE_PASARELA = 'stripe';

    /** @return array{pagoId:string, ticketId:string, estado:string, stripe:?array{paymentUrl:?string, qrDataUri:?string, gatewayRef:?string}} */
    public static function cobro(CobroRacionResult $r): array
    {
        $p = $r->pasarela;
        return [
            'pagoId' => $r->pagoId,
            'ticketId' => $r->ticketId,
            'estado' => $r->estado,
            self::CLAVE_PASARELA => $p === null ? null : [   // null si no es QR; con URL+QR si sí
                'paymentUrl' => $p->paymentUrl,
                'qrDataUri' => $p->qrDataUri,
                'gatewayRef' => $p->referencia,
            ],
        ];
    }

    /** @return array{paid:bool, status:string, amount?:int, sessionId?:string, currency?:string, error?:string} */
    public static function verificacion(VerificacionPagoResult $r): array
    {
        $out = ['paid' => $r->paid, 'status' => $r->status];
        if ($r->amount !== null) $out['amount'] = $r->amount;
        if ($r->sessionId !== null) $out['sessionId'] = $r->sessionId;
        if ($r->currency !== null) $out['currency'] = $r->currency;
        if ($r->error !== null) $out['error'] = $r->error;
        return $out;
    }

    /** @return array{url?:string, tipo?:string, pdf?:?string, error?:string} */
    public static function factura(FacturaResult $r): array
    {
        if (!$r->disponible()) return ['error' => (string)$r->error];
        $out = ['url' => $r->url, 'tipo' => $r->tipo];
        // Una factura formal siempre trae la clave pdf (aunque sea null); un recibo no la trae.
        if ($r->tipo === FacturaResult::TIPO_FACTURA) $out['pdf'] = $r->pdf;
        return $out;
    }
}
