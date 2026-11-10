# Estándares de código — Comedor Universitario UAGRM

Cómo cumple el proyecto cada criterio de calidad. Los ejemplos son código real de los servicios: menú e inventario (Java 21), comensales y reservas (TypeScript / NestJS), pagos y tickets (PHP 8.3), IA (Python 3.12 / FastAPI), web (React / Next.js) y móvil (Flutter / Dart).

---

## 1. Nomenclatura

Nombres descriptivos y con la misma convención en todo el proyecto: `camelCase` para variables y métodos (Java, TypeScript, PHP, Dart), `snake_case` en Python, `PascalCase` para clases y `UPPER_SNAKE_CASE` para constantes. El dominio se nombra en español con el vocabulario del modelo ICONIX (Reserva, Porción, Beca, Insumo, Lote). Los términos técnicos van en inglés (`idempotencyKey`, `Repository`).

```php
// pagos-tickets-service (PHP): constantes y métodos que dicen qué hacen
public const ESTADO_COBRADO = 'cobrado';
public const METODO_BECA = 'beca';
```

```python
# ia-ml-service (Python): funciones con verbo + objeto
def sectores_referencia(pred: np.ndarray) -> dict[str, int]: ...
def comparar(zonas_ref: dict[str, int], pred_bandeja: np.ndarray, nombres: dict[str, str]) -> tuple[list[dict], float]: ...
```

## 2. Formato e indentación

4 espacios en Java, PHP y Python (PSR-12 y PEP 8) y 2 espacios en TypeScript y Dart. Cada archivo contiene una sola responsabilidad: un caso de uso, un adaptador o un componente. El formato se verifica con herramientas: `flutter analyze` (con `flutter_lints`) termina en *"No issues found!"*, `tsc --noEmit` compila sin errores y PHP declara `declare(strict_types=1)` al inicio de cada archivo.

## 3. Comentarios y documentación

Cada módulo empieza con un comentario que explica **para qué existe**. Las funciones públicas llevan docstring o JSDoc cuando su intención no es obvia. Los comentarios explican el *por qué* y no repiten el código.

```python
def comparar(zonas_ref: dict[str, int], pred_bandeja: np.ndarray, nombres: dict[str, str]) -> tuple[list[dict], float]:
    """([{clase, nombre, restante}], promedio). Solo los sectores que existían en la foto llena."""
```

```typescript
/** Ubicación del comedor: la que guardó el administrador; si todavía no guardó ninguna, la de la configuración. */
export class LugarComedorGateway extends UbicacionComedor { ... }
```

Además, cada servicio tiene su `README.md` y los diagramas ICONIX (casos de uso, secuencia, clases, colaboración) en `comedor-infra/docs/iconix`.

## 4. Organización del código

Todos los backends siguen **arquitectura hexagonal (puertos y adaptadores)**: el dominio no conoce la base de datos ni HTTP.

```
pagos-tickets-service/app/          ia-ml-service/app/
├── Domain/          reglas puras   ├── domain/          reglas puras (numpy, sin I/O)
├── Application/     casos de uso   ├── application/     casos de uso + puertos (Protocol)
└── Infrastructure/  adaptadores    └── infrastructure/  FastAPI, SQLite, TensorFlow, S3
```

En el frontend cada vista es un componente (`app/reportes/client.tsx`, `app/cnn/client.tsx`). La lógica que no es de pantalla vive en `app/lib/` (motor de reportes, exportación, voz).

## 5. Legibilidad

Funciones cortas, con un solo propósito y con validaciones tempranas (*guard clauses*), para que la lectura vaya de arriba hacia abajo sin anidar condiciones.

```typescript
// MarcarAsistencia (comensales-reservas-service): fuera del radio, la marca no se registra
const comedor = await this.comedor.obtener();
const distancia = Asistencia.distancia(lat, lon, comedor.lat, comedor.lon);
if (distancia > comedor.radioM) {
  throw new ReglaDeNegocioError(`Estás a ${metros(distancia)} del comedor: solo se puede marcar dentro de ${metros(comedor.radioM)}.`);
}
```

## 6. Manejo de errores y excepciones

El dominio lanza **excepciones propias**, que no conocen HTTP. La capa de interfaz las traduce al código HTTP correcto, así el usuario siempre recibe un mensaje claro y el servicio no se cae.

```typescript
// Filtro de NestJS: error del negocio -> respuesta HTTP
const [status, error] = e instanceof NoEncontradoError ? [404, 'Not Found']
  : e instanceof PermisoDenegadoError ? [403, 'Forbidden'] : [409, 'Conflict'];
host.switchToHttp().getResponse().status(status).json({ statusCode: status, message: e.message, error });
```

En el frontend, toda llamada a la red va en `try / catch / finally`. El `finally` libera los estados de carga, tanto si la operación salió bien como si falló. Los servicios externos (Stripe, Telegram, Firebase) se tratan como "mejor esfuerzo": si fallan, se registra el problema pero el flujo principal continúa.

## 7. Seguridad del código

- **Inyección SQL:** nunca se concatenan datos en una consulta. Se usan consultas preparadas con parámetros.

  ```php
  // PdoObligacionRepository (PHP / PDO)
  $s = $this->pdo->prepare('SELECT * FROM obligaciones_proveedor WHERE orden_id = :orden AND proveedor = :prov');
  $s->execute([':orden' => $o->ordenId, ':prov' => $o->proveedor]);
  ```

  ```typescript
  // TypeORM (NestJS): parámetros con nombre
  .where('b.estudianteId = :id', { id: estudianteId })
  ```

- **Validación de entradas:** DTOs en NestJS, *records* en Java y modelos **Pydantic** en FastAPI rechazan las peticiones mal formadas. Además, las entidades validan sus propias reglas en el constructor (por ejemplo, un `Porcion` con cupo negativo no puede existir).
- **Autenticación y roles:** Keycloak (OIDC). El api-gateway verifica la firma del token y pasa el rol a los servicios. Las acciones sensibles comprueban el rol en el caso de uso, por ejemplo: *"Solo un administrador puede cambiar la ubicación del comedor"*.
- **Desbordamientos:** los lenguajes usados manejan la memoria automáticamente (sin aritmética de punteros). Las entradas tienen límites: rango de latitud y longitud, radio entre 50 y 5.000 m, hasta 5 sectores por bandeja y prototipos de tamaño fijo (1 a 8 vectores de 147 números).
- **Datos en tránsito:** HTTPS con certificado Let's Encrypt en todos los puntos de entrada. Las fotos se suben a S3 con cifrado del lado del servidor (`AES256`).

## 8. Gestión de recursos

Las conexiones y transacciones se abren y se cierran en un bloque que garantiza su liberación aunque ocurra un error. En Python se usa `with`, en TypeScript `finally`, y las conexiones a PostgreSQL las administran los *pools* de TypeORM, Spring y PDO.

```python
# ia-ml-service: la transacción se cierra sola al salir del bloque
def referencia(self, ref_id: str) -> dict | None:
    with self._tx() as c:
        r = c.execute("SELECT * FROM ref_porcion WHERE id=?", (ref_id,)).fetchone()
        return self._con_sectores(r) if r else None
```

Las fotos se reducen a 900 px antes de guardarlas. Los modelos de IA se cargan una sola vez en memoria y se reutilizan. En Kubernetes, cada contenedor tiene límites de memoria y CPU.

## 9. Modularidad y reutilización

- **Una sola fuente de verdad:** el catálogo de reportes se define una vez en el servidor y viaja a la web y al celular junto con la copia de datos. El modelo de IA de sectores es el mismo archivo para el servidor y para la app.
- **Composición en un solo lugar:** cada servicio conecta sus puertos con sus adaptadores en una raíz de composición: `CasosDeUsoConfig` (Spring), `AppModule` (NestJS), `bootstrap/container.php` (PHP-DI) y `app/main.py` (FastAPI).

```typescript
// AppModule (NestJS): los casos de uso no conocen Nest, se arman con una fábrica reutilizable
const casoDeUso = <T>(clase: new (...args: any[]) => T, ...inject: any[]) =>
  ({ provide: clase, useFactory: (...deps: any[]) => new clase(...deps), inject });
```

## 10. Eficiencia y rendimiento

- Cálculo **vectorizado** con numpy en la IA de visión, en vez de bucles por píxel:
  ```python
  def alisar(mascara: np.ndarray) -> np.ndarray:
      m = mascara.reshape(REJ, REJ).astype(np.float32)
      p = np.pad(m, 1, mode="edge")
      return (sum(p[i:i + REJ, j:j + REJ] for i in range(3) for j in range(3)) >= 5).ravel()
  ```
- **Índices** en las columnas que se consultan (`@Index() usuario` en asistencias) y consultas con límite (`take: limite`).
- **Caché:** los archivos web con hash se guardan un año (`immutable`). Los reportes se calculan en el navegador o en el teléfono con una copia local (IndexedDB), sin ir al servidor en cada consulta.
- Las tablas en pantalla muestran 300 filas; el PDF y el Excel incluyen todas.

## 11. Consistencia

Las mismas convenciones y la misma arquitectura en los cinco servicios y las tres interfaces. El motor de reportes existe en tres lenguajes (Python, JavaScript y Dart) y una **prueba de paridad** con 66 consultas garantiza que los tres den exactamente el mismo resultado. Lo mismo pasa con el análisis de bandejas entre el servidor y el celular.

## 12. Pruebas y validación

| Servicio | Pruebas automáticas |
|---|---|
| IA (Python, pytest) | 152 |
| Comensales y reservas (NestJS, Jest) | 56 |
| Menú e inventario (Java, JUnit) | 40 |
| Pagos y tickets (PHP, PHPUnit) | 40 |
| Reportes web (Node test) | 109 (incluye paridad) |
| App móvil (Flutter test) | 72 (incluye paridad con el servidor) |

Hay pruebas **unitarias** del dominio y los casos de uso con dobles de prueba en memoria, pruebas **de integración** de los adaptadores y del ensamblado de dependencias (*"todos los controladores se pueden construir"*), y **análisis estático** (`tsc`, `flutter analyze`, tipado estricto en PHP). Las pruebas corren en cada *push* con GitHub Actions (workflow `ci`), junto al despliegue continuo (`cd`).

## 13. Mantenibilidad

Gracias a los puertos y adaptadores, cambiar una pieza externa no toca el dominio. Por ejemplo, la ubicación del comedor pasó de variables de entorno a una tabla editable por el administrador cambiando solo el adaptador (`LugarComedorGateway`): el caso de uso `MarcarAsistencia` siguió igual. De la misma forma se puede cambiar Stripe por la pasarela simulada, o S3 por el disco local. El despliegue continuo (CI/CD) y las pruebas automáticas permiten corregir y ampliar el sistema con bajo riesgo de romper lo que ya funciona.
