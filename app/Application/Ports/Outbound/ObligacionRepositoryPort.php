<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\ObligacionProveedor;

interface ObligacionRepositoryPort
{
    /** Guarda si no existe una con la misma orden y proveedor. Devuelve la que quedó guardada. */
    public function guardarSiNoExiste(ObligacionProveedor $o): ObligacionProveedor;

    /** @return ObligacionProveedor[] */
    public function todas(): array;
}
