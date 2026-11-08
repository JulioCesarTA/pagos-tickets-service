<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

interface PaymentGatewayPort
{
    /** @return array{ok:bool, referencia?:string, error?:string} */
    public function cobrarQr(float $monto, string $referencia): array;
    public function devolver(string $referencia): bool;
    /** @return array{paid:bool,status:string,amount?:int,sessionId?:string,error?:string} */
    public function verificarPago(string $referencia): array;
    /** @return array{url?:string, tipo?:string, error?:string} factura (o recibo) hospedada por la pasarela */
    public function facturaUrl(string $referencia): array;
}
