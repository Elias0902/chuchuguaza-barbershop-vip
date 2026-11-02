# Chuchuguaza Barbershop

> Sistema y página web creados por **Elias Armas** · [eliasarmas.dev](https://eliasarmas.dev). Software propietario: ver [LICENSE](LICENSE).

Sistema de gestión interna de la barbería Chuchuguaza (17 años de trayectoria: barbería, venta de productos y bebidas, y cursos).

- **Tecnología:** PHP 8.1+ nativo con MVC, PDO (MySQL), Composer y recursos locales.
- **Landing pública:** `http://localhost/chuchuguaza-barber-shop/` (sin sesión abre `landing-chuchuguaza/index.html`).
- **Acceso interno:** botón *Acceso interno* de la landing → `index.php?url=autenticator`.

## Instalación

1. Importar en MySQL (XAMPP) los respaldos `bd/negocioBD/backups/chuchuguasa_db.sql` y `bd/seguridadBD/backups/chuchuguasa_seguridad.sql` (solo en una instancia vacía: crean tablas sin `IF NOT EXISTS`).
   En una base ya importada, aplicar las migraciones de `bd/negocioBD/migraciones/` en orden (se pueden repetir sin duplicar datos):

   ```bash
   mysql -u root --default-character-set=utf8mb4 chuchuguasa_db < bd/negocioBD/migraciones/001_politicas.sql
   mysql -u root --default-character-set=utf8mb4 chuchuguasa_db < bd/negocioBD/migraciones/002_buzon_sugerencias.sql
   ```

2. Instalar dependencias y generar el autoload:

   ```bash
   php composer.phar install
   ```

3. (Opcional) Crear `.env` en la raíz para cambiar credenciales: `DB_HOST_NEGOCIO`, `DB_NAME_NEGOCIO`, `DB_USER_NEGOCIO`, `DB_PASS_NEGOCIO`, las mismas con `_SECURITY`, `MONEDA_SIMBOLO` y `ZONA_HORARIA`.

Para entrar se necesita en `chuchuguasa_seguridad` un usuario con `status = 1`, `email_verificado = 1` y un rol activo. Los demás usuarios se crean desde el módulo **Usuarios**.

### Datos de prueba

Para probar el sistema con unos 4 meses de actividad inventada (40 clientes, 4 barberos, servicios, productos, ~320 tickets con sus pagos, citas, cierres de caja, nómina, cursos y promociones):

```bash
php bd/negocioBD/semillas/datos_prueba.php
```

El script usa los mismos modelos del sistema, así los datos pasan por las validaciones reales (stock, totales, estados de pago, caja). Solo corre si la base del negocio está vacía y al final ejecuta el primer análisis del buzón. Para borrar todo y cargar los datos reales (se conservan las políticas de servicio):

```bash
php bd/negocioBD/semillas/datos_prueba.php --vaciar VACIAR
```

## Estructura

```text
/
├── index.php                 # Punto de entrada único: carga Composer, config y FrontController
├── config/
│   ├── config.php            # Constantes y lectura de .env
│   ├── route.php             # url → controlador (use ...::class)
│   └── modulos.php           # Menú lateral: grupos, títulos, íconos y descripciones
├── app/
│   ├── core/                 # App\Core: base del MVC
│   │   ├── FrontController.php
│   │   ├── Conexion.php      # PDO encapsulado (negocio y seguridad)
│   │   ├── BaseModel.php     # Bitácora compartida
│   │   ├── CrudInterface.php # Contrato consultar/obtener/registrar/modificar/eliminar/restaurar
│   │   ├── CrudModel.php     # Validación, SQL preparado, eliminación lógica, restauración
│   │   ├── BaseController.php
│   │   ├── CrudController.php# Acciones JSON + vista + reporte PDF
│   │   ├── Autorizacion.php  # Permisos por rol y sincronización de módulos
│   │   └── *Exception.php
│   ├── controllers/          # App\Controllers: un controlador por módulo
│   ├── models/               # App\Models: un modelo por tabla
│   ├── models/Analisis/      # Motor de análisis, reglas, hallazgos y estadísticas
│   ├── helpers/              # App\Helpers: ReportePdf (FPDF), Vista, Formato y Grafico
│   ├── views/                # moduloView, dashboardHome, perfilView, accesoInterno, partials, error
│   └── Utils/serve_asset.php # Compresión de CSS/JS e imágenes (vía .htaccess)
├── assets/
│   ├── css/panel.css, fonts.min.css (Font Awesome local)
│   ├── js/panel.js, modulo.js, perfil.js, permisos.js, buzon.js
│   └── img/perfiles/         # Fotos de perfil subidas
├── bd/                       # Respaldos SQL de las dos bases
├── landing-chuchuguaza/      # Landing pública
├── tests/e2e/                # Pruebas con Selenium (Node), aparte de la aplicación
└── vendor/                   # Composer (setasign/fpdf)
```

## Cómo funciona una petición

1. Todo entra por `index.php?url=<modulo>&action=<accion>`.
2. `App\Core\FrontController` busca la clase en `config/route.php`, valida la sesión y llama `ejecutar($accion)`.
3. Cada controlador de módulo hereda de `CrudController`, que resuelve:

| action | Método | Qué hace |
|---|---|---|
| *(vacía)* | GET | Muestra la vista del módulo |
| `consultar` | GET | Devuelve la tabla en JSON (la vista la consulta por AJAX) |
| `obtener` | GET | Un registro (`&id=`) |
| `registrar` | POST | Inserta |
| `modificar` | POST | Actualiza un registro activo |
| `eliminar` | POST | **Eliminación lógica** (`status = 0`) |
| `restaurar` | POST | Vuelve a `status = 1` |
| `reporte` | GET | PDF del listado (`&filtro=activos|eliminados|todos`) |

Las peticiones POST usan JSON y el token CSRF en la cabecera `X-CSRF-Token`. Todas las operaciones quedan en la **bitácora**.

## Principios de POO aplicados

- **Encapsulamiento:** las conexiones PDO y credenciales son privadas en `Conexion`; los modelos exponen getters (`clave()`, `singular()`...) y guardan su estado interno como `private`.
- **Abstracción:** `CrudInterface` define el contrato; `CrudModel`, `CrudController` y `BaseController` son abstractas con métodos abstractos (`campos()`, `columnas()`, `crearModelo()`, `ejecutar()`).
- **Herencia:** `Cliente → CrudModel → BaseModel → Conexion`; `ClientesController → CrudController → BaseController`; `ReportePdf → FPDF`.
- **Polimorfismo:** el FrontController llama `ejecutar()` sin saber qué controlador es; los modelos reescriben `sqlConsulta()`, `reglasNegocio()`, `antesDeEliminar()`, `despuesDeGuardar()`...; `Bitacora` reescribe `registrar()`/`modificar()` para ser de solo lectura.

## Módulos

| Grupo | Módulos |
|---|---|
| Atención | Citas (completar/cancelar, sin choques de horario), Tickets de atención (detalle de servicios y productos, total y stock automáticos, cobrar), Pagos (actualizan el estado del ticket) |
| Catálogos | Clientes, Barberos (cédula como clave, un solo principal), Servicios, Productos y bebidas, Categorías, Promociones, Métodos de pago |
| Caja y finanzas | Cierres de caja (una caja abierta a la vez; cierre con monto según sistema y diferencia), Gastos generales, Retiros de dinero (desglose por método), Nómina (cortes y comisiones calculados de los tickets pagados) |
| Academia | Cursos, Inscripciones (propone el costo del curso y controla el saldo) |
| Análisis | Buzón de sugerencias (las genera el sistema), Análisis de ventas (indicadores comparados y gráficos) |
| Página pública | Políticas de servicio (las activas se publican solas en la landing, en el orden indicado) |
| Seguridad | Usuarios (contraseña con hash, desbloqueo), Roles (con matriz de permisos), Bitácora (solo consulta) |
| Cuenta | Mi perfil (foto, datos y contraseña), en el menú del usuario arriba a la derecha |

Todos los módulos con tabla de estado usan **eliminación lógica**: nada se borra físicamente; los registros eliminados se ven en la pestaña *Eliminados* y se pueden restaurar. Algunas eliminaciones se bloquean para mantener la coherencia (barbero principal, categoría con productos activos, ticket con pagos, caja con gastos o retiros, rol en uso, tu propio usuario).

## Permisos por rol

Cada módulo del menú tiene cuatro permisos: **Consultar**, **Registrar**, **Modificar** y **Eliminar** (eliminar incluye restaurar). Se asignan por rol en **Seguridad → Roles → botón Permisos** (escudo), con una matriz por módulo.

- **Dónde se guardan:** base de seguridad, tablas `modulos`, `permisos` y `accesos`. Quitar un permiso deja el acceso con `status = 0`.
- **Sincronización automática:** `App\Core\Autorizacion` crea o actualiza las filas de `modulos` a partir de `config/modulos.php` y los cuatro permisos. Un módulo nuevo en el menú aparece solo en la matriz.
- **Primer arranque:** si `accesos` está vacía, el rol del primer usuario que entra recibe acceso total, para que el sistema no quede bloqueado.
- **Módulos nuevos:** cuando se agrega un módulo al menú, los roles que pueden modificar Roles lo reciben completo; al resto hay que asignárselo en la matriz.
- **Cómo se aplica:** el FrontController pregunta al controlador qué módulo protege (`moduloProtegido()`) y qué permiso exige la acción (`permisoRequerido()`): ver/consultar/obtener/reporte → consultar, registrar → registrar, modificar y acciones propias → modificar, eliminar/restaurar → eliminar. Sin permiso responde 403 (página o JSON).
- **Interfaz:** el menú y el inicio solo muestran los módulos que el rol puede consultar, y los botones Nuevo, Modificar, Eliminar, Restaurar y acciones propias se ocultan sin el permiso correspondiente.
- **Protecciones:** no puedes quitarle a tu propio rol Consultar y Modificar en Roles, ni cambiarte tu propio rol desde Usuarios. Los permisos se leen de la base en cada petición, así un cambio se aplica sin cerrar sesión.
- La tabla `permisos_usuario` (excepciones por usuario) existe pero no se usa todavía.

## Agregar un módulo nuevo

1. **Modelo** en `app/models` que extienda `App\Core\CrudModel`: define `$tabla`, `$clave`, `$modulo`, `$singular` y `campos()` (tipos: `texto`, `textoLargo`, `nombre`, `email`, `telefono`, `cedula`, `usuario`, `clave`, `entero`, `decimal`, `fecha`, `fechahora`, `opcion`, `relacion`, `booleano`, `detalle`). Reescribe los ganchos que necesites.
2. **Controlador** en `app/controllers` que extienda `App\Core\CrudController`: `ruta()`, `crearModelo()` y `columnas()`.
3. Registrar la ruta en `config/route.php` y el ítem del menú en `config/modulos.php`.

La vista (`moduloView.php` + `assets/js/modulo.js`) arma la tabla y el formulario con esa configuración.

## Composer

`composer.json` solo requiere `setasign/fpdf` (reportes PDF) y define el autoload PSR-4: `App\Core`, `App\Controllers`, `App\Models`, `App\Helpers`. Después de crear clases nuevas no hace falta nada más; si se agrega un namespace, ejecutar `php composer.phar dump-autoload -o`.

## Limpieza del proyecto anterior

Se eliminó todo el código heredado del sistema anterior que no pertenece a la barbería (módulos, asistente con IA, vistas, JS y CSS de su plantilla, imágenes, librería FPDF manual y dependencias de Composer que no se usaban). Hay un respaldo comprimido fuera del proyecto en `C:\xampp\respaldos\chuchuguaza_legado_2026-11-01.zip`.

## Políticas de servicio en la landing

Las políticas se administran en **Página pública → Políticas de servicio** (registrar, modificar, eliminación lógica y orden). La landing las pide a `index.php?url=publico&action=politicas` (JSON de solo lectura, sin sesión) y las muestra en la sección *Políticas de servicio*, con su enlace en el menú y la fecha de la última actualización. Si no hay políticas activas, la sección se oculta. El texto inicial es un borrador cargado por la migración `001_politicas.sql` para revisarlo y ajustarlo.

## Buzón de sugerencias del sistema

Nadie escribe en el buzón: el **motor de análisis** (`App\Models\Analisis\MotorAnalisis`, sin servicios externos) revisa los datos y deja sugerencias para mejorar y vender más.

- **Cuándo analiza:** solo, cada 6 horas como máximo (se revisa al abrir cualquier página del panel), o al pulsar **Analizar ahora** en el buzón. Cada ejecución queda en `analisis_ejecuciones`.
- **Qué revisa** (una clase por regla en `app/models/Analisis/Reglas`, todas heredan de `ReglaAnalisis` y sobrescriben `evaluar()`):
  - *VentasTendencia:* ingresos de los últimos 30 días frente a los 30 anteriores y caída del ticket promedio.
  - *VentaCruzada:* qué tan seguido se vende una bebida o producto con el servicio, combos de servicios que se piden juntos y producto estrella.
  - *CobrosPendientes:* tickets sin cobrar, saldos de cursos y nóminas por pagar.
  - *StockProductos:* productos agotados o por agotarse.
  - *ClientesInactivos:* clientes que no vuelven hace más de 45 días y clientes más fieles.
  - *DemandaSemanal:* día más flojo, hora pico y barbero sobrecargado.
  - *ServiciosYPromociones:* servicios sin ventas y falta de promociones vigentes.
  - *AgendaYCaja:* citas canceladas o sin marcar, caja abierta de días anteriores y faltantes en los cierres.
- **Ciclo de una sugerencia:** *Nueva → Leída → Aplicada* o *Descartada*. Si la situación desaparece, el sistema la marca como *Resuelta* sola. Cada situación tiene una clave (por ejemplo `stock-agotado`): mientras está abierta no se repite, y si se aplicó o descartó no vuelve a salir en 15 días.
- **Dónde se ve:** el ícono de buzón en la barra superior con el número de nuevas, el panel *Sugerencias del sistema* en el inicio y en el análisis de ventas, y el módulo **Análisis → Buzón de sugerencias**.
- Para agregar una sugerencia nueva basta con crear otra clase que herede de `ReglaAnalisis` y sumarla en `MotorAnalisis::predeterminado()`.

## Análisis de ventas

**Análisis → Análisis de ventas** muestra, para 30, 90 o 365 días: atenciones, ingresos, ticket promedio y clientes atendidos (con su variación frente al período anterior), y gráficos de ingresos por mes, servicios y productos más vendidos, ingresos por método de pago, atenciones por día, por hora y por barbero. Los gráficos (`App\Helpers\Grafico`) son HTML y CSS sin librerías, con detalle al pasar el mouse o al enfocarlos con el teclado, y una tabla con los datos.

## Modo claro y modo oscuro

El botón de sol o luna de la barra superior cambia el tema del panel y lo recuerda en el navegador. Si nunca se eligió, se usa el del sistema operativo. Todos los colores salen de variables CSS en `assets/css/panel.css` (`:root` para el oscuro y `[data-tema="claro"]` para el claro), y el tema se aplica antes de pintar la página para que no parpadee.

## Pruebas automáticas con Selenium

En `tests/e2e/` hay pruebas de punta a punta que abren el navegador, inician sesión y, en **cada módulo**, hacen **registrar → modificar → eliminar** (eliminación lógica, comprobando que el registro pase a *Eliminados*). Están escritas en JavaScript con `selenium-webdriver` y el ejecutor de pruebas que trae Node, aparte del código PHP: Composer sigue siendo solo para los PDF.

**Preparación (una sola vez)**, con Node 20 o superior y Chrome o Edge instalados:

```bash
cd tests/e2e
npm install
```

Copia `tests/e2e/.env.example` como `tests/e2e/.env` y escribe el usuario y la contraseña de un usuario con rol Administrador. Ese archivo no se sube al repositorio. El driver del navegador lo descarga Selenium solo la primera vez.

**Ejecutar** (con XAMPP encendido y los datos de prueba cargados):

```bash
npm test
```

Para correr un solo módulo, por el nombre de su prueba:

```bash
npm run modulo -- "Clientes"
```

Opciones en `.env`: `NAVEGADOR=edge` para usar Edge, `OCULTO=1` para correr sin ventana y `PAUSA_MS=500` para ir más lento y seguir los pasos con la vista. Si un paso falla, los siguientes del mismo módulo se omiten y queda una captura de pantalla en `tests/e2e/capturas/`.

| Archivo | Qué prueba |
|---|---|
| `01-catalogos` | Clientes, barberos, servicios, categorías, productos, promociones y métodos de pago |
| `02-atencion` | Citas, tickets (con detalle y precio automático) y pagos (monto con el saldo del ticket) |
| `03-finanzas` | Cierres de caja, gastos, retiros (con desglose) y nómina |
| `04-academia` | Cursos e inscripciones (elige un curso y un cliente sin inscripción activa) |
| `05-pagina-y-seguridad` | Políticas de servicio, usuarios, roles y que la bitácora registre esas operaciones |
| `06-analisis-y-cuenta` | Buzón (analizar, marcar leída, descartar, reabrir), análisis de ventas, inicio, modo claro/oscuro y Mi perfil |

La estructura sigue el patrón *Page Object*: `lib/panel.js` sabe manejar el panel (iniciar sesión, llenar formularios, buscar en la tabla, confirmar) y cada prueba solo describe los datos del módulo.

**Datos que dejan las pruebas:** los registros llevan «Selenium» en el nombre y quedan como eliminados (eliminación lógica), así que no aparecen en las listas de activos. La prueba de cierres de caja cierra la caja abierta del día y al final deja abierta una nueva. Conviene correrlas sobre los datos de prueba, no sobre los reales.

## Pendiente

- Permisos por usuario (excepciones sobre su rol) con la tabla `permisos_usuario`.
- Recuperación de contraseña por correo.
