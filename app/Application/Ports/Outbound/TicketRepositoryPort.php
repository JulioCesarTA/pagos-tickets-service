<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Ticket;

interface TicketRepositoryPort
{
    public function save(Ticket $t): void;
    public function findById(string $id): ?Ticket;
}
