<?php
declare(strict_types=1);

namespace App\Application\Ports\Inbound;

interface VerificarPagoUseCase
{
    /** @return array{paid:bool,status:string,amount?:int,sessionId?:string,error?:string} */
    public function execute(string $referencia): array;
}
