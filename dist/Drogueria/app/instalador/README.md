# Instalación en el equipo del cliente

Cómo se arma la memoria USB con la que se instala el punto de venta, y por
qué está montado así.

## Qué recibe el cliente

Una memoria USB con dos archivos a la vista y una carpeta de apoyo:

```
INSTALAR.bat          <- lo único que se ejecuta
LEEME.txt             <- instrucciones en una página
recursos/
  app/                <- la aplicación Laravel, sin dependencias de desarrollo
  php/                <- PHP 8.4 portable de Windows, con su php.ini
  scripts/            <- herramientas que quedan junto al programa instalado
  plantillas/         <- el .env que se genera en el equipo del cliente
  instalar.ps1        <- el asistente
```

`INSTALAR.bat` pide permisos de administrador y lanza `instalar.ps1`, que
copia todo a `C:\Drogueria`, genera la configuración, prepara la base de
datos, comparte la impresora y crea el acceso directo del escritorio.

**Después de instalar, la memoria se puede retirar**: no queda nada
apuntando a ella.

## Reconstruir el paquete

Después de cambiar el código:

```powershell
.\instalador\construir-paquete.ps1 -Usb D:
```

El script compila los assets de Vite, copia la aplicación, instala las
dependencias sin las de desarrollo, descarga el PHP portable si hace falta,
verifica que todas las extensiones carguen y vuelca el resultado en la USB.

Sin `-Usb` sólo arma la carpeta (`%USERPROFILE%\Drogueria-USB`). Con
`-Limpiar` rehace todo desde cero, incluida la descarga de PHP.

Reinstalar encima de una instalación existente **conserva** la base de
datos y el `.env`: sirve para actualizar al cliente sin perderle las ventas.

## Compatibilidad: qué necesita el equipo del cliente

**Nada preinstalado.** El paquete lleva su propio PHP, sus dependencias y
sus bibliotecas. No hace falta internet ni en la instalación ni en el uso.

Requisitos reales, todos comprobados por el instalador, que avisa con una
explicación si alguno falla:

| Requisito | Situación |
|---|---|
| Windows de **64 bits** | El instalador **aborta** si es de 32; PHP es x64 |
| **.NET Framework 4.6.2+** | Windows 10 lo trae desde 2016 y Windows 11 siempre. Si falta, avisa y deja usar el modo diagnóstico |
| **WebView2 Runtime** | De fábrica en Windows 11. Si falta, el programa lo detecta y ofrece abrirse en el navegador |
| Visual C++ Redistributable | **Ya no hace falta**: las tres DLL que PHP necesita viajan junto a `php.exe` |

Ese último punto era un fallo real. El `.zip` oficial de PHP **no incluye**
`vcruntime140.dll`, `vcruntime140_1.dll` ni `msvcp140.dll`: da por hecho que
el equipo tiene instalado el redistribuible de Visual C++. En un Windows
recién formateado no está y **PHP ni arranca**. Ahora se copian junto a
`php.exe`, donde Windows busca primero, y `construir-paquete.ps1` **aborta
la construcción** si no las encuentra.

## Decisiones y por qué

### SQLite en vez de MySQL

Toda la información vive en `app\database\database.sqlite`. No hay servidor
que instalar, arrancar ni mantener, y el respaldo es copiar un archivo.

`config/database.php` activa **WAL** y un `busy_timeout` de 5 segundos:
Livewire dispara varias peticiones solapadas por venta y, sin eso, SQLite
devuelve *"database is locked"* en plena caja.

### Servidor embebido de PHP

El programa corre sobre `php artisan serve` en `127.0.0.1:8347`, escuchando
sólo en local. Es una caja con un cajero, así que no hace falta nginx ni
Apache, y así no hay servicios de Windows que administrar.

### `Drogueria.exe`: una ventana, no un navegador

Lo que abre el cajero es una aplicación de Windows normal, con su icono en
la barra de tareas. Por dentro arranca el servidor de PHP como proceso hijo
y muestra la aplicación en un control **WebView2**. No se ve un navegador en
ningún momento, y no hace falta internet.

Es un WinForms de ~400 líneas (`app-escritorio/Drogueria.cs`) que
`construir-paquete.ps1` compila con el **`csc.exe` que ya trae Windows**
(.NET Framework 4.x): no hay que instalar Visual Studio ni el SDK de .NET
para armar el paquete. Las tres bibliotecas de WebView2 se sacan del paquete
oficial de NuGet y viajan junto al `.exe`.

Qué resuelve, además de la apariencia:

- **Captura la salida de PHP.** Si el servidor no arranca, enseña el error
  real en pantalla en vez de un «no se pudo» inútil.
- **Sin proxy.** Las comprobaciones a `127.0.0.1` van con `Proxy = null`; un
  proxy configurado en el equipo puede tragarse peticiones al propio
  ordenador.
- **Una sola instancia.** Un mutex evita que un segundo doble clic levante
  otro servidor sobre el mismo puerto; en su lugar trae al frente la ventana
  abierta.
- **Cierra lo que abre.** Al cerrar la ventana mata el proceso de PHP y sus
  hijos. Y por si muere de malas maneras —lo matan desde el Administrador
  de tareas, se cuelga, se va la luz—, el hijo va dentro de un **objeto Job
  de Windows** con `KILL_ON_JOB_CLOSE`: el sistema operativo se lo lleva por
  delante pase lo que pase. Sin eso, el `php.exe` huérfano se queda con el
  puerto y el siguiente arranque falla con *"Failed to listen on
  127.0.0.1:8347"*.
- **Se recupera de un puerto ocupado.** Si al arrancar hay algo en el puerto
  que no responde como el punto de venta, mata los `php.exe` huérfanos de su
  propia carpeta —sólo los suyos, no los de un XAMPP ajeno—. Si aun así PHP
  se queja del puerto, prueba el siguiente, hasta ocho. El número de puerto
  es un detalle interno y nada de lo que ve el cajero depende de que sea el
  8347.

### No preguntar si un puerto "se puede usar": lanzarlo y mirar

Esto costó tres intentos fallidos, y merece quedar escrito.

La primera versión comprobaba el puerto abriendo una **conexión** de prueba,
que responde a *"¿hay alguien escuchando?"*. No es la pregunta: un puerto sin
nadie escuchando puede no admitir un enlace nuevo si acaba de cerrarse y
Windows todavía lo retiene.

La segunda versión lo "arregló" comprobando con un **enlace** de prueba —
abrir un socket y cerrarlo justo antes de arrancar PHP. Salió peor: Windows
tarda un instante en soltar ese socket, así que **la propia comprobación era
lo que hacía fallar a PHP**. El síntoma era desconcertante: fallaba siempre
desde el programa y nunca al arrancar el servidor a mano.

La versión que funciona no comprueba nada antes. Lanza PHP, espera dos
segundos, y mira qué pasó: si responde, listo; si murió quejándose del
puerto, prueba el siguiente; si murió por otra cosa, enseña el error real sin
cambiar de puerto, porque cambiarlo no arreglaría nada.

Predecir si una operación va a funcionar es frágil cuando la predicción
tiene efectos secundarios. Observar lo que pasó, no.
- **Sin intérpretes de por medio.** El acceso directo apunta al `.exe`
  directamente, así que ni `wscript.exe` ni las políticas de scripts de
  Windows pueden estorbar.

Antes de esto el lanzador era un VBScript que abría Chrome en modo `--app`.
Dio dos problemas: `wscript.exe` lee un `.vbs` sin BOM como ANSI y destrozaba
las tildes de los avisos, y al lanzarlo desde el instalador (elevado) Chrome
arrancaba como administrador, no podía abrir el perfil del usuario y se
cerraba en silencio, sin que apareciera ninguna ventana.

Si en el equipo falta el **WebView2 Runtime** (Windows 10 sin actualizar; en
Windows 11 viene de fábrica), el programa lo detecta, lo avisa y ofrece un
botón para abrir en el navegador: la caja nunca se queda sin poder vender.

### OPcache y el fallo de ASLR

`php.ini` fija `opcache.file_cache` y `opcache.file_cache_fallback=1`, y el
instalador reescribe esa ruta a una carpeta real de la instalación.

**No es opcional.** Sin ese caché en disco, PHP en Windows aborta de forma
intermitente con:

```
Fatal Error Opcode handlers are unusable due to ASLR
```

y cuando pasa, el programa no abre. Depende de cómo Windows haya repartido
la memoria en ese arranque: puede funcionar cien veces y fallar la ciento
una. Se reprodujo durante el desarrollo, ocho veces seguidas.

Apagar OPcache también lo evitaría, pero cuesta caro: medido en este
proyecto, **500 ms por página contra 40-90 ms** con la caché activa. En un
POS eso es medio segundo de espera por cada escaneo.

### Codificación: la regla que no se puede romper

**Los `.bat` van en ASCII puro, sin BOM y sin `chcp`.** No es una
preferencia de estilo; es la causa de un fallo que rompía la instalación por
completo.

`cmd.exe` lee un archivo por lotes **por posición de byte**, no línea por
línea: después de cada orden vuelve al archivo y busca desde el byte
siguiente. Con la página de códigos en UTF-8 (`chcp 65001`), cada tilde
ocupa dos bytes pero el analizador cuenta uno, así que la posición se
desplaza y a partir de ahí **cada línea se lee cortada por delante**: `REM`
se convierte en `EM`, luego en `M`, y la consola se llena de

```
"EM" no se reconoce como un comando interno o externo
```

Cuando el corte le toca a la comprobación de administrador de
`INSTALAR.bat`, el script no ve su propia condición de salida y **se
relanza a sí mismo en bucle infinito**.

Lo que rompe **no es `chcp` por sí solo**, sino `chcp` junto a caracteres
multibyte en el propio archivo. Sin caracteres de dos bytes no hay desfase
posible. De ahí las reglas:

- **Ningún `.bat` lleva caracteres fuera de ASCII**, ni siquiera en
  comentarios. Es la regla de la que dependen todas las demás.
- Las herramientas **sí** hacen `chcp 65001`, porque PHP escribe en UTF-8 y
  sin eso sus mensajes salen con las tildes partidas.
- **`INSTALAR.bat` no hace `chcp`**: ahí quien escribe es PowerShell, que ya
  codifica bien contra la página OEM, y es el archivo que entró en bucle.
- **Los `.bat` van con finales de línea CRLF.** Con sólo LF, cmd.exe tolera
  lo sencillo pero se atraganta con `call`, las etiquetas y los bloques
  `if (...)`, que es de lo que depende `INSTALAR.bat`.
- **Ningún `.bat` usa `set /p`.** Con la consola en UTF-8, `set /p` de
  cmd.exe **no lee absolutamente nada**: la variable queda vacía y el script
  sigue como si no se hubiera escrito. Comprobado. Por eso las herramientas
  interactivas son lanzadores de tres líneas y toda la conversación ocurre
  en PowerShell (`scripts/herramientas/*.ps1`).
- `INSTALAR.bat` pasa un argumento `elevado` al relanzarse con UAC: si la
  elevación falla, el guardia corta en seco en vez de reintentar en bucle.

Aparte, **todos los `.ps1` van en UTF-8 con BOM**. Windows PowerShell 5.1 lee
un `.ps1` sin BOM como ANSI y convierte cada tilde en dos caracteres basura
(`Droguería` → `Droguer├¡a`).

Y las carpetas `scripts/` y `plantillas/` se copian con `robocopy /MIR`, no
con `Copy-Item`: al renombrar una herramienta, `Copy-Item` dejaba la versión
vieja conviviendo con la nueva en la memoria, y con ella un fallo ya
corregido. En el equipo del cliente hace lo mismo un inventario
(`herramientas/inventario.txt`), porque ahí la raíz tiene cosas que no se
pueden borrar.

### `revisar.ps1`

Todas esas reglas están comprobadas por un script, y
`construir-paquete.ps1` lo ejecuta antes de armar nada: **si algo falla, no
hay paquete**. Se puede lanzar suelto:

```powershell
.\instalador\revisar.ps1
```

Comprueba la codificación y los finales de línea de cada `.bat`, el BOM de
cada `.ps1`, que no se cuelen construcciones que sólo existen en PowerShell
7 (`Test-Path -Force`, `??`, `?.`, `Join-String`…), que las herramientas que
se invocan entre sí existan, que cada marcador de la plantilla tenga quien
lo reemplace y que `php.ini` cargue las extensiones necesarias.

Cada regla está ahí porque algo falló de verdad, no por completitud
teórica.

### Impresión ESC/POS

`App\Services\ReceiptPrinter` arma el tiquete y se lo manda a la impresora
por `WindowsPrintConnector`.

Windows no deja que PHP le escriba bytes crudos a una impresora USB salvo
que esté **compartida**; el conector escribe a `\\EQUIPO\RECURSO`. Por eso
el instalador la comparte (por defecto como `TIQUETERA`) y guarda ese
nombre en `PRINTER_NAME`.

escpos-php sólo admite nombres de letras, números, guiones y espacios
sueltos: con una tilde o un punto se niega a imprimir. El instalador valida
el nombre y ofrece volver a compartirla si el que tiene no sirve.

El ancho por defecto es de 42 columnas (rollo de 80 mm, el nativo de la
Epson TM-T20II). Con la guía plástica angosta de 58 mm son 32 columnas:
`PRINTER_COLUMNS` en el `.env`, o elegir la opción 2 al instalar.

Si la impresión falla, la venta **ya quedó guardada**: `PosComponent`
atrapa el error, avisa al cajero y deja el comprobante en pantalla para
reintentar o imprimirlo desde el navegador.

### Sin inicio de sesión

Es deliberado: una caja, una persona. Como no hay contraseña, quien se
siente en ese equipo puede ver las ventas y tocar el inventario; si el
computador queda al alcance del público, conviene ponerle contraseña a
Windows. Está advertido en el `LEEME.txt`.

## Diagnóstico

Herramientas que quedan en `C:\Drogueria`:

| Herramienta | Para qué |
|---|---|
| `Respaldar datos` | Copia de seguridad (`VACUUM INTO`, funciona con el programa abierto) |
| `Configurar impresora` | Lista las impresoras, cambia `PRINTER_NAME` e imprime una prueba |
| `Cerrar programa` | Detiene sólo el PHP de esta instalación |
| `Cargar datos de ejemplo` | Catálogo de prueba (respalda antes de cargar) |
| `Iniciar (modo diagnostico)` | Arranca mostrando los errores en pantalla |

Desde la carpeta del programa:

```
php\php.exe app\artisan drogueria:impresora --listar   # impresoras y sus nombres compartidos
php\php.exe app\artisan drogueria:impresora            # tiquete de prueba
php\php.exe app\artisan drogueria:respaldar            # copia de seguridad
```

El tiquete de prueba imprime las tildes y una regla numerada del ancho del
rollo: si la regla se dobla de línea, sobran columnas en `PRINTER_COLUMNS`.

## Desinstalar

Borrar `C:\Drogueria` y el acceso directo del escritorio. No hay nada en el
registro de Windows ni servicios instalados.
