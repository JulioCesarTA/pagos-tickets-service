<?php
declare(strict_types=1);

namespace App\Application\Ports\Outbound;

interface EventBusPort
{
    public function publish(string $topic, array $payload): void;
}
