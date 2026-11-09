<?php
declare(strict_types=1);

use App\Application\Ports\Inbound\{CobrarRacionUseCase, ConsultarPagosUseCase, DevolverPagoUseCase, ObtenerFacturaUseCase, VerificarPagoUseCase};
use App\Application\Ports\Outbound\{PagoRepositoryPort, TicketRepositoryPort, PaymentGatewayPort, EventBusPort, IdGeneratorPort};
use App\Infrastructure\Adapters\Id\RamseyUuidGenerator;
use App\Application\Services\{CobrarRacionService, ConsultarPagosService, DevolverPagoService, ObtenerFacturaService, VerificarPagoService};
use App\Infrastructure\Adapters\Messaging\SnsEventBusAdapter;
use App\Infrastructure\Adapters\Persistence\postgres\{PdoPagoRepository, PdoTicketRepository};
use App\Infrastructure\Adapters\QrBancario\FakeQrBancarioAdapter;
use App\Infrastructure\Adapters\QrBancario\StripeQrAdapter;
use Aws\Sns\SnsClient;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $b): void {
    $b->addDefinitions([
        LoggerInterface::class => function () {
            $l = new Logger('pagos-tickets');
            $l->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
            return $l;
        },
        PDO::class => function () {
            $host = getenv('DB_HOST') ?: 'postgres-pagos';
            $port = getenv('DB_PORT') ?: '5432';
            $db   = getenv('DB_DATABASE') ?: 'pagos_tickets';
            $u    = getenv('DB_USERNAME') ?: 'pagos';
            $p    = getenv('DB_PASSWORD') ?: 'pagos123';
            $dsn  = "pgsql:host=$host;port=$port;dbname=$db";
            return new PDO($dsn, $u, $p, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        },
        SnsClient::class => function () {
            $cfg = [
                'region' => getenv('AWS_REGION') ?: 'us-east-1',
                'version' => 'latest',
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID') ?: 'test',
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
                ],
            ];
            if ($endpoint = getenv('AWS_ENDPOINT_URL')) $cfg['endpoint'] = $endpoint;
            return new SnsClient($cfg);
        },
        PagoRepositoryPort::class => DI\autowire(PdoPagoRepository::class),
        TicketRepositoryPort::class => DI\autowire(PdoTicketRepository::class),
        PaymentGatewayPort::class => function (\Psr\Container\ContainerInterface $c) {
            $key = getenv('STRIPE_SECRET_KEY') ?: '';
            if ($key && str_starts_with($key, 'sk_')) {
                return new StripeQrAdapter($key, $c->get(\Psr\Log\LoggerInterface::class));
            }
            return new FakeQrBancarioAdapter();
        },
        EventBusPort::class => DI\autowire(SnsEventBusAdapter::class),
        IdGeneratorPort::class => DI\autowire(RamseyUuidGenerator::class),
        CobrarRacionUseCase::class => DI\autowire(CobrarRacionService::class),
        DevolverPagoUseCase::class => DI\autowire(DevolverPagoService::class),
        ConsultarPagosUseCase::class => DI\autowire(ConsultarPagosService::class),
        ObtenerFacturaUseCase::class => DI\autowire(ObtenerFacturaService::class),
        VerificarPagoUseCase::class => DI\autowire(VerificarPagoService::class),
        \App\Application\Ports\Outbound\ObligacionRepositoryPort::class => DI\autowire(\App\Infrastructure\Adapters\Persistence\postgres\PdoObligacionRepository::class),
        \App\Application\Ports\Inbound\ObligacionesProveedorUseCase::class => DI\autowire(\App\Application\Services\ObligacionesProveedorService::class),
    ]);
};
