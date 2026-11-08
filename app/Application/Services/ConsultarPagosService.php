<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Inbound\ConsultarPagosUseCase;
use App\Application\Ports\Outbound\PagoRepositoryPort;
use App\Domain\Pago;

final class ConsultarPagosService implements ConsultarPagosUseCase
{
    public function __construct(private readonly PagoRepositoryPort $pagos) {}

    public function listar(): array { return $this->pagos->todos(); }

    public function ver(string $pagoId): ?Pago { return $this->pagos->findById($pagoId); }
}
