# Despliegue en Vercel

Guía para dejar la demostración en línea. Son unos 15 minutos la primera vez;
después cada cambio se publica solo con hacer `git push`.

## Qué se agregó al proyecto

| Archivo | Para qué sirve |
| --- | --- |
| `api/index.php` | Punto de entrada. Vercel sólo ejecuta PHP dentro de `/api`; este archivo delega en `public/index.php`. |
| `vercel.json` | Le dice a Vercel que use el runtime de PHP 8.3, que sirva `public/build` y `public/images` como archivos estáticos y que todo lo demás pase por Laravel. |
| `.vercelignore` | Evita subir `vendor/`, `node_modules/`, tests y logs. Composer reinstala las dependencias en el servidor. |
| `.env.vercel.example` | Lista de variables de entorno que hay que pegar en el panel de Vercel. |
| `database/seeders/DemoSeeder.php` | Catálogo de 28 productos, sus lotes y un mes de ventas, para que el cliente no entre a un sistema vacío. |

---

## 1. Crear la base de datos (Neon)

Vercel ejecuta la aplicación en funciones sin disco de escritura, así que SQLite
no sirve: las ventas se perderían. Neon da un Postgres gratis y permanente.

1. Entrar a <https://neon.tech> y crear la cuenta (se puede con GitHub).
2. **Create project** → nombre `drogueria`, región **AWS us-east-2** o la más
   cercana a la región donde quede el proyecto de Vercel.
3. Copiar la **connection string**. Se ve así:

   ```
   postgresql://neondb_owner:CLAVE@ep-algo-123456.us-east-2.aws.neon.tech/neondb?sslmode=require
   ```

Guardarla: se usa en los pasos 2 y 4.

## 2. Cargar el esquema y los datos de demostración

Esto se hace **una sola vez y desde tu máquina** — Vercel no ejecuta comandos de
Artisan durante el despliegue. En PowerShell, dentro de la carpeta del proyecto:

```powershell
$env:DB_CONNECTION = "pgsql"
$env:DB_URL = "postgresql://neondb_owner:CLAVE@ep-algo-123456.us-east-2.aws.neon.tech/neondb?sslmode=require"

php artisan migrate --force --seed
```

Las variables sólo viven en esa ventana de PowerShell, así que tu `.env` local
sigue apuntando a tu MySQL de siempre.

Para volver a dejar la demo como recién instalada (por ejemplo después de que el
cliente la use), el mismo comando con `migrate:fresh --force --seed`.

### Empezar de cero con datos reales

Cuando la droguería va a cargar su propio catálogo, `drogueria:vaciar` deja las
tablas del negocio en blanco sin tocar el esquema. Los contadores se reinician,
así que la primera venta vuelve a ser la factura `FAC-00000001`:

```powershell
php artisan drogueria:vaciar            # inventario y ventas
php artisan drogueria:vaciar --ventas   # sólo las ventas
```

> **Si la conexión a Neon falla con `SQLSTATE[08006] server closed the
> connection unexpectedly`**, el PHP de ese equipo trae una libpq anterior a la
> 14: no envía SNI y Neon corta la conexión antes de autenticar. No es la
> contraseña. Se resuelve corriendo el comando desde un equipo con PostgreSQL 14
> o superior instalado, o aplicando el SQL con el driver HTTP de Neon
> (`@neondatabase/serverless` desde Node, que va por HTTPS).

## 3. Compilar los estilos y subir el repositorio

Los assets de Vite se versionan a propósito: Vercel los sirve como archivos
estáticos y la función PHP necesita `public/build/manifest.json`.

```powershell
npm run build

git add -A
git commit -m "feat: configuracion de despliegue en Vercel"
```

Crear un repositorio en GitHub y empujarlo:

```powershell
git remote add origin https://github.com/TU-USUARIO/drogueria.git
git push -u origin main
```

> Cada vez que cambies algo de CSS o JS hay que volver a correr `npm run build`
> y commitear `public/build`, si no el sitio publicado queda con los estilos
> viejos.

## 4. Crear el proyecto en Vercel

1. Entrar a <https://vercel.com> → **Add New → Project** → importar el
   repositorio de GitHub.
2. En **Framework Preset** dejar **Other**. No tocar Build Command ni Output
   Directory: `vercel.json` ya lo define todo.
3. Abrir **Environment Variables** y pegar las variables de
   `.env.vercel.example`, marcando *Production*, *Preview* y *Development*.

   Las que hay que completar sí o sí:

   - `APP_KEY` — la que ya tienes en tu `.env` local, o una nueva generada con
     `php artisan key:generate --show` (se copia completa, incluido `base64:`).
   - `DB_URL` — la cadena de conexión de Neon del paso 1.
   - `APP_URL` — se deja provisional y se corrige en el paso 5.

4. **Deploy**.

## 5. Ajustar la URL

Cuando termine el despliegue, Vercel asigna un dominio como
`drogueria-xxxx.vercel.app`. Hay que ponerlo en `APP_URL`
(Settings → Environment Variables) y volver a desplegar desde
**Deployments → ... → Redeploy** para que los enlaces y el comprobante
apunten al dominio correcto.

Si quieres un enlace más presentable para el cliente, en
**Settings → Domains** se puede cambiar el subdominio a algo como
`drogueria-demo.vercel.app` sin costo.

---

## Qué queda funcionando en línea

- Punto de venta con búsqueda por nombre o código de barras, carrito, IVA,
  descuento, cálculo de cambio y descuento de stock por lote (FEFO).
- Inventario con alertas de stock bajo y de lotes próximos a vencer.
- Reportes por rango de fechas, método de pago y productos más vendidos.
- Comprobante de 80 mm, que se imprime desde el navegador con Ctrl+P.

## Limitaciones conocidas de la demostración

Ninguna afecta lo que el cliente va a ver, pero conviene tenerlas claras:

- **No hay impresión directa a la impresora térmica.** El paquete `escpos-php`
  imprime conectándose al puerto de la impresora, y el servidor de Vercel no
  tiene acceso a la red local del negocio. En línea el comprobante se imprime
  desde el navegador; la impresión directa sólo funcionará cuando el sistema se
  instale en el computador del local.
- **No hay usuarios ni inicio de sesión.** Cualquiera con el enlace entra
  directo al punto de venta. Para la demostración es lo cómodo; antes de poner
  esto en producción real hay que agregar autenticación.
- **No hay subida de archivos ni colas.** El disco del servidor es de sólo
  lectura salvo `/tmp`, que se borra en cada arranque.
- **Primer acceso lento.** Después de un rato sin uso la función se apaga y la
  primera carga tarda un par de segundos. Las siguientes son inmediatas.

## Si algo falla

- **Pantalla en blanco o error 500**: en Vercel, pestaña **Logs** del
  despliegue. Con `APP_DEBUG=false` el detalle sólo aparece ahí.
- **Se ve sin estilos**: falta `public/build` en el repositorio. Correr
  `npm run build` y commitear la carpeta.
- **`SQLSTATE[08006] connection refused`**: revisar que `DB_URL` esté completa y
  termine en `?sslmode=require`.
- **`Vite manifest not found`**: mismo caso que "se ve sin estilos".
