<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\Id;

use App\Application\Ports\Outbound\IdGeneratorPort;
use Ramsey\Uuid\Uuid;

/** Adaptador de salida: identificadores UUID v4 con ramsey/uuid (las tablas guardan el id como UUID). */
final class RamseyUuidGenerator implements IdGeneratorPort
{
    public function nuevoId(): string
    {
        return Uuid::uuid4()->toString();
    }
}
