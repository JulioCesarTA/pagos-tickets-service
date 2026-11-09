<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

/** Genera identificadores únicos (UUID v4 en texto) para las entidades nuevas. */
interface IdGeneratorPort
{
    public function nuevoId(): string;
}
