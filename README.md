# pagos-tickets-service

Cobro de cada ración (QR bancario o efectivo), cobertura de becas, facturas y devoluciones.

| | |
|---|---|
| Stack | PHP 8.3 · Slim + PHP-DI |
| Arquitectura de código | Hexagonal (puertos y adaptadores) |
| Base de datos | PostgreSQL (propia) |
| Nube de despliegue | GCP (GKE) |
| Puerto local | `8083` (dentro del contenedor, `8080`) |

`app/Domain` es PHP puro; los casos de uso están en `app/Application` y los adaptadores (Postgres, Stripe, SNS) en `app/Infrastructure`.

## Probar

```bash
./vendor/bin/phpunit
docker build -t pagos-tickets-service .
```

## Ejecutar con todo el sistema

Este repositorio es solo un microservicio. Para levantar el sistema completo (bases de datos, Keycloak, gateways, frontends) usa
[`comedor-infra`](https://github.com/JulioCesarTA/comedor-infra) con los demás repositorios clonados en la misma carpeta:

```bash
git clone https://github.com/JulioCesarTA/comedor-infra && cd comedor-infra && bash clonar-todo.sh
docker compose up -d --build
```

## CI/CD

- `.github/workflows/ci.yml`: pruebas y construcción de la imagen en cada push y pull request.
- `.github/workflows/cd.yml`: construye y sube la imagen al registro de GCP y aplica el manifiesto de
  `comedor-infra/infra/k8s/gcp/pagos-tickets-service/`. Secretos necesarios: ver el propio archivo.
