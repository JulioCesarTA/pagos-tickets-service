<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapters\Messaging;

use App\Application\Ports\Outbound\EventBusPort;
use Aws\Sns\SnsClient;
use Psr\Log\LoggerInterface;

final class SnsEventBusAdapter implements EventBusPort
{
    public function __construct(
        private readonly SnsClient $sns,
        private readonly LoggerInterface $log,
        private readonly string $region = 'us-east-1',
    ) {}

    public function publish(string $topic, array $payload): void
    {
        $arn = sprintf('arn:aws:sns:%s:%s:%s', $this->region, getenv('AWS_ACCOUNT_ID') ?: '000000000000', $topic);
        try {
            $this->sns->publish([
                'TopicArn' => $arn,
                'Message' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $this->log->info("SNS publicado {$payload['type']} -> $topic");
        } catch (\Throwable $e) {
            $this->log->error("SNS falló publicando a $topic: " . $e->getMessage());
        }
    }
}
