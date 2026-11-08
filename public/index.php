<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use App\Infrastructure\Http\Controllers\PagosController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$containerBuilder = new ContainerBuilder();
(require __DIR__ . '/../bootstrap/container.php')($containerBuilder);
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->get('/health', fn (Request $r, Response $s) => (function () use ($s) {
    $s->getBody()->write(json_encode(['status' => 'UP', 'service' => 'pagos-tickets']));
    return $s->withHeader('Content-Type', 'application/json');
})());

$app->post('/pagos/cobrar', [PagosController::class, 'cobrar']);
$app->post('/pagos/{id}/devolver', [PagosController::class, 'devolver']);
$app->get('/pagos/verificar/{referencia}', [PagosController::class, 'verificar']);
$app->get('/pagos', [PagosController::class, 'listar']);
$app->get('/pagos/recibo/{referencia}', [PagosController::class, 'recibo']);
$app->get('/pagos/{id}',[PagosController::class, 'ver']);

// CORS para que lo pueda llamar directo el portal (React en 3002/3000)
$app->add(function (Request $req, $handler) {
    $res = $handler->handle($req);
    return $res
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
});
$app->options('/{routes:.+}', fn(Request $r, Response $s) => $s);

$app->run();
