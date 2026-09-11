// ---------------------------------------------------------------------------
// Punto de venta de la droguería — ventana de escritorio.
//
// Sustituye al lanzador de VBScript y al navegador: abre una ventana normal
// de Windows, con su icono en la barra de tareas, y dentro muestra la
// aplicación. El cajero no ve un navegador en ningún momento.
//
// Lo que hace, en orden:
//   1. Arranca "php artisan serve" como proceso hijo, sin ventana.
//   2. Espera a que responda, capturando lo que PHP escriba.
//   3. Muestra la aplicación dentro de un control WebView2.
//   4. Si el servidor no arranca, enseña la salida real de PHP en vez de un
//      "no se pudo" que no le sirve a nadie.
//   5. Al cerrar la ventana, detiene el servidor.
//
// Se compila con el csc.exe que ya trae Windows; no hace falta instalar
// Visual Studio ni el SDK de .NET. Ver construir-paquete.ps1.
// ---------------------------------------------------------------------------

using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace Drogueria
{
    internal static class Programa
    {
        // Sin esto, cada doble clic en el icono levantaría otro servidor
        // sobre el mismo puerto: el segundo falla y el cajero ve un error.
        private static Mutex _instanciaUnica;

        [STAThread]
        private static void Main()
        {
            bool esNueva;
            _instanciaUnica = new Mutex(true, @"Local\DrogueriaPuntoDeVenta", out esNueva);

            if (!esNueva)
            {
                VentanaExistente.TraerAlFrente();
                return;
            }

            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new VentanaPrincipal());
        }
    }

    /// <summary>
    /// Trae al frente la ventana del programa que ya estaba abierto.
    /// </summary>
    internal static class VentanaExistente
    {
        [System.Runtime.InteropServices.DllImport("user32.dll")]
        private static extern bool SetForegroundWindow(IntPtr hWnd);

        [System.Runtime.InteropServices.DllImport("user32.dll")]
        private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

        private const int SW_RESTORE = 9;

        public static void TraerAlFrente()
        {
            Process actual = Process.GetCurrentProcess();

            foreach (Process p in Process.GetProcessesByName(actual.ProcessName))
            {
                if (p.Id == actual.Id || p.MainWindowHandle == IntPtr.Zero)
                {
                    continue;
                }

                ShowWindow(p.MainWindowHandle, SW_RESTORE);
                SetForegroundWindow(p.MainWindowHandle);
                return;
            }
        }
    }

    /// <summary>
    /// Ata los procesos hijos a la vida de este programa.
    ///
    /// Cerrar la ventana ya detiene el servidor, pero eso no cubre que el
    /// programa muera de malas maneras: lo matan desde el Administrador de
    /// tareas, se cuelga, se va la luz. En esos casos el php.exe sobrevive,
    /// se queda con el puerto, y al volver a abrir el programa aparece
    /// "Failed to listen on 127.0.0.1:8347".
    ///
    /// Un objeto Job de Windows con KILL_ON_JOB_CLOSE lo resuelve de raíz:
    /// cuando este proceso desaparece, sea como sea, el sistema operativo se
    /// lleva por delante a los que estén dentro del job.
    /// </summary>
    internal sealed class TrabajoWindows : IDisposable
    {
        [System.Runtime.InteropServices.DllImport("kernel32.dll", CharSet = System.Runtime.InteropServices.CharSet.Unicode)]
        private static extern IntPtr CreateJobObject(IntPtr atributos, string nombre);

        [System.Runtime.InteropServices.DllImport("kernel32.dll")]
        private static extern bool SetInformationJobObject(IntPtr job, int claseInfo, IntPtr info, uint tamano);

        [System.Runtime.InteropServices.DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool AssignProcessToJobObject(IntPtr job, IntPtr proceso);

        [System.Runtime.InteropServices.DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool CloseHandle(IntPtr objeto);

        private const int ExtendedLimitInformation = 9;
        private const uint LimitKillOnJobClose = 0x2000;

        [System.Runtime.InteropServices.StructLayout(System.Runtime.InteropServices.LayoutKind.Sequential)]
        private struct LimitesBasicos
        {
            public long TiempoUsuario;
            public long TiempoTotal;
            public uint Banderas;
            public IntPtr MinimoTrabajo;
            public IntPtr MaximoTrabajo;
            public uint LimiteProcesos;
            public IntPtr Afinidad;
            public uint Prioridad;
            public uint Clase;
        }

        [System.Runtime.InteropServices.StructLayout(System.Runtime.InteropServices.LayoutKind.Sequential)]
        private struct ContadoresES
        {
            public ulong Lecturas;
            public ulong Escrituras;
            public ulong Otras;
            public ulong BytesLeidos;
            public ulong BytesEscritos;
            public ulong BytesOtros;
        }

        [System.Runtime.InteropServices.StructLayout(System.Runtime.InteropServices.LayoutKind.Sequential)]
        private struct LimitesExtendidos
        {
            public LimitesBasicos Basicos;
            public ContadoresES Contadores;
            public UIntPtr MemoriaProceso;
            public UIntPtr MemoriaTrabajo;
            public UIntPtr PicoProceso;
            public UIntPtr PicoTrabajo;
        }

        private IntPtr _job = IntPtr.Zero;

        public bool Disponible { get { return _job != IntPtr.Zero; } }

        public TrabajoWindows()
        {
            try
            {
                _job = CreateJobObject(IntPtr.Zero, null);

                if (_job == IntPtr.Zero)
                {
                    return;
                }

                var limites = new LimitesExtendidos();
                limites.Basicos.Banderas = LimitKillOnJobClose;

                int tamano = System.Runtime.InteropServices.Marshal.SizeOf(typeof(LimitesExtendidos));
                IntPtr buffer = System.Runtime.InteropServices.Marshal.AllocHGlobal(tamano);

                try
                {
                    System.Runtime.InteropServices.Marshal.StructureToPtr(limites, buffer, false);

                    if (!SetInformationJobObject(_job, ExtendedLimitInformation, buffer, (uint)tamano))
                    {
                        CloseHandle(_job);
                        _job = IntPtr.Zero;
                    }
                }
                finally
                {
                    System.Runtime.InteropServices.Marshal.FreeHGlobal(buffer);
                }
            }
            catch
            {
                // Sin job se sigue funcionando: queda el cierre ordenado de la
                // ventana y la limpieza de huérfanos al arrancar.
                _job = IntPtr.Zero;
            }
        }

        public void Adoptar(Process proceso)
        {
            if (_job == IntPtr.Zero || proceso == null)
            {
                return;
            }

            try
            {
                AssignProcessToJobObject(_job, proceso.Handle);
            }
            catch
            {
                // Idem: es una red de seguridad, no el mecanismo principal.
            }
        }

        public void Dispose()
        {
            if (_job != IntPtr.Zero)
            {
                CloseHandle(_job);
                _job = IntPtr.Zero;
            }
        }
    }

    /// <summary>
    /// Arranca y detiene el servidor local de PHP.
    /// </summary>
    internal sealed class Servidor
    {
        private readonly string _carpetaBase;
        private readonly StringBuilder _salida = new StringBuilder();
        private readonly object _candado = new object();
        private readonly TrabajoWindows _trabajo = new TrabajoWindows();
        private Process _proceso;

        public int Puerto { get; private set; }

        public string Url
        {
            get { return "http://127.0.0.1:" + Puerto; }
        }

        public Servidor(string carpetaBase)
        {
            _carpetaBase = carpetaBase;
            Puerto = LeerPuerto();
        }

        /// <summary>Lo que PHP haya escrito, para poder enseñarlo si falla.</summary>
        public string Salida
        {
            get { lock (_candado) { return _salida.ToString(); } }
        }

        private int LeerPuerto()
        {
            // El instalador escribe el puerto elegido junto al programa.
            try
            {
                string archivo = Path.Combine(_carpetaBase, "puerto.txt");

                if (File.Exists(archivo))
                {
                    int valor;
                    if (int.TryParse(File.ReadAllText(archivo).Trim(), out valor) &&
                        valor > 0 && valor < 65536)
                    {
                        return valor;
                    }
                }
            }
            catch
            {
                // Un puerto ilegible no es motivo para no abrir: se usa el fijo.
            }

            return 8347;
        }

        public string ComprobarInstalacion()
        {
            string php = Path.Combine(_carpetaBase, @"php\php.exe");
            string artisan = Path.Combine(_carpetaBase, @"app\artisan");

            if (!File.Exists(php))
            {
                return "No se encontró PHP en:\n" + php;
            }

            if (!File.Exists(artisan))
            {
                return "No se encontró la aplicación en:\n" + artisan;
            }

            if (!File.Exists(Path.Combine(_carpetaBase, @"app\.env")))
            {
                return "Falta el archivo de ajustes (app\\.env).\n" +
                       "Vuelva a ejecutar el instalador.";
            }

            return null;
        }

        /// <summary>
        /// ¿Hay algo escuchando en el puerto, responda bien o no?
        /// </summary>
        public bool PuertoOcupado()
        {
            try
            {
                using (var prueba = new System.Net.Sockets.TcpClient())
                {
                    var intento = prueba.BeginConnect("127.0.0.1", Puerto, null, null);

                    if (!intento.AsyncWaitHandle.WaitOne(700))
                    {
                        return false;
                    }

                    prueba.EndConnect(intento);

                    return true;
                }
            }
            catch
            {
                return false;
            }
        }

        /// <summary>
        /// ¿Se cayó PHP quejándose de que no pudo tomar el puerto?
        /// </summary>
        private bool SeQuejoDelPuerto()
        {
            return Salida.IndexOf("Failed to listen", StringComparison.OrdinalIgnoreCase) >= 0;
        }

        /// <summary>
        /// Mata los php.exe de ESTA instalación que hayan quedado sueltos.
        ///
        /// Pasa cuando el programa muere sin cerrar bien: lo matan desde el
        /// Administrador de tareas, se cuelga, se corta la luz. El servidor
        /// sobrevive, se queda con el puerto, y el siguiente arranque falla
        /// con "Failed to listen on 127.0.0.1".
        ///
        /// Sólo los de esta carpeta: en un equipo con XAMPP u otro programa
        /// en PHP, barrer todos los php.exe se llevaría cosas ajenas.
        /// </summary>
        public int LimpiarHuerfanos()
        {
            string php = Path.Combine(_carpetaBase, @"php\php.exe");
            int muertos = 0;

            foreach (Process p in Process.GetProcessesByName("php"))
            {
                try
                {
                    if (!string.Equals(p.MainModule.FileName, php, StringComparison.OrdinalIgnoreCase))
                    {
                        continue;
                    }

                    p.Kill();
                    p.WaitForExit(3000);
                    muertos++;
                }
                catch
                {
                    // Un proceso que no se deja inspeccionar o matar no debe
                    // impedir que se revisen los demás.
                }
            }

            return muertos;
        }

        /// <summary>
        /// Levanta el servidor. Si ya había uno respondiendo (por ejemplo el
        /// del inicio automático de Windows), se reutiliza.
        ///
        /// El método es: lanzar y mirar qué pasa. Nada de comprobar antes si
        /// el puerto "se puede usar".
        ///
        /// Ese atajo se probó y salió caro: la comprobación abría y cerraba
        /// un socket en el mismo puerto justo antes de arrancar PHP, y
        /// Windows tarda un instante en soltarlo. La propia comprobación era
        /// lo que hacía fallar a PHP con "Failed to listen". Predecir si un
        /// puerto va a funcionar es intrínsecamente frágil; observar lo que
        /// pasó, no.
        ///
        /// Si PHP se queja del puerto, se prueba el siguiente. El número es
        /// un detalle interno: nada de lo que ve el cajero depende de que
        /// sea el 8347.
        /// </summary>
        public void Iniciar()
        {
            const int cuantosPuertos = 8;
            const int intentosPorPuerto = 2;

            int primero = Puerto;

            if (Responde())
            {
                return;
            }

            // Un servidor huérfano de una sesión que no se cerró bien sigue
            // agarrado al puerto. Si hay algo ahí que no contesta como el
            // punto de venta, se limpia antes de empezar.
            if (PuertoOcupado() && LimpiarHuerfanos() > 0)
            {
                System.Threading.Thread.Sleep(1000);

                if (Responde())
                {
                    return;
                }
            }

            for (int salto = 0; salto < cuantosPuertos; salto++)
            {
                Puerto = primero + salto;

                if (Puerto > 65500)
                {
                    break;
                }

                for (int intento = 1; intento <= intentosPorPuerto; intento++)
                {
                    ReiniciarSalida();
                    LanzarProceso();

                    if (!MurioAlArrancar())
                    {
                        return;
                    }

                    // Si no fue por el puerto, cambiar de puerto no arregla
                    // nada: el error real está en la salida y hay que
                    // enseñarlo tal cual.
                    if (!SeQuejoDelPuerto())
                    {
                        return;
                    }

                    // Mismo puerto una segunda vez: si sólo era que Windows
                    // no lo había soltado del todo, un respiro basta.
                    if (intento < intentosPorPuerto)
                    {
                        System.Threading.Thread.Sleep(1500);
                    }
                }
            }

            // Agotados todos: se deja el puerto original en el mensaje, que
            // es el que el usuario reconoce.
            Puerto = primero;
        }

        /// <summary>
        /// Espera un instante a ver si el proceso recién lanzado se cae solo.
        /// </summary>
        private bool MurioAlArrancar()
        {
            for (int i = 0; i < 16; i++)
            {
                System.Threading.Thread.Sleep(125);

                if (Responde())
                {
                    return false;
                }

                if (Murio())
                {
                    return true;
                }
            }

            // Sigue vivo y todavía arrancando: no es un fallo de arranque.
            return false;
        }

        /// <summary>
        /// Olvida la salida del intento anterior, para que un fallo pasajero
        /// ya superado no acabe mezclado en el mensaje de error final.
        /// </summary>
        private void ReiniciarSalida()
        {
            lock (_candado)
            {
                _salida.Clear();
            }
        }

        private void LanzarProceso()
        {
            string php = Path.Combine(_carpetaBase, @"php\php.exe");
            string appDir = Path.Combine(_carpetaBase, "app");

            var inicio = new ProcessStartInfo
            {
                FileName = php,
                Arguments = "\"" + Path.Combine(appDir, "artisan") + "\"" +
                            " serve --host=127.0.0.1 --port=" + Puerto,
                // artisan resuelve storage\ y database\ desde la carpeta de
                // trabajo, así que tiene que arrancar parado en app\.
                WorkingDirectory = appDir,
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
            };

            _proceso = new Process { StartInfo = inicio };
            _proceso.OutputDataReceived += Acumular;
            _proceso.ErrorDataReceived += Acumular;

            _proceso.Start();

            // Que Windows se encargue de matarlo si este programa muere sin
            // poder hacerlo él mismo.
            _trabajo.Adoptar(_proceso);

            // Hay que leer las dos salidas: si se redirigen y no se vacían,
            // el búfer se llena y PHP se queda bloqueado a media jornada.
            _proceso.BeginOutputReadLine();
            _proceso.BeginErrorReadLine();
        }

        private void Acumular(object emisor, DataReceivedEventArgs e)
        {
            if (e.Data == null)
            {
                return;
            }

            lock (_candado)
            {
                // Con un tope: si PHP se pone a escribir sin parar, esto no
                // puede crecer hasta llenar la memoria del equipo.
                if (_salida.Length < 20000)
                {
                    _salida.AppendLine(e.Data);
                }
            }
        }

        public bool Responde()
        {
            try
            {
                var peticion = (HttpWebRequest)WebRequest.Create(Url + "/up");
                peticion.Timeout = 2000;
                peticion.Method = "GET";

                // Sin proxy: si el equipo tiene uno configurado, o "detectar
                // automáticamente", una petición a 127.0.0.1 puede acabar
                // saliendo por él y no llegar nunca.
                peticion.Proxy = null;

                using (var respuesta = (HttpWebResponse)peticion.GetResponse())
                {
                    return (int)respuesta.StatusCode < 500;
                }
            }
            catch (WebException ex)
            {
                // Que conteste un error HTTP también significa que está vivo.
                return ex.Response != null;
            }
            catch
            {
                return false;
            }
        }

        /// <summary>¿Murió el proceso nada más arrancar?</summary>
        public bool Murio()
        {
            try
            {
                return _proceso != null && _proceso.HasExited;
            }
            catch
            {
                return false;
            }
        }

        public void Detener()
        {
            if (_proceso == null)
            {
                return;
            }

            try
            {
                if (!_proceso.HasExited)
                {
                    // /T para llevarse también los hijos: el servidor
                    // embebido de PHP lanza uno por petición.
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = "taskkill",
                        Arguments = "/PID " + _proceso.Id + " /T /F",
                        UseShellExecute = false,
                        CreateNoWindow = true,
                    }).WaitForExit(5000);
                }
            }
            catch
            {
                // Si no se deja matar, el proceso queda huérfano pero no
                // impide cerrar la ventana; "Cerrar programa.bat" lo remata.
            }

            // Se espera a que Windows suelte el puerto de verdad. Sin esto,
            // cerrar y volver a abrir enseguida hace que el servidor nuevo
            // se encuentre el puerto a medio liberar y no pueda arrancar.
            for (int i = 0; i < 20 && PuertoOcupado(); i++)
            {
                System.Threading.Thread.Sleep(150);
            }
        }
    }

    internal sealed class VentanaPrincipal : Form
    {
        private readonly Servidor _servidor;
        private readonly string _carpetaBase;
        private WebView2 _vista;
        private Label _mensaje;

        public VentanaPrincipal()
        {
            _carpetaBase = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\');
            _servidor = new Servidor(_carpetaBase);

            Text = "Droguería — Punto de Venta";
            StartPosition = FormStartPosition.CenterScreen;
            Size = new Size(1400, 900);
            MinimumSize = new Size(900, 600);
            WindowState = FormWindowState.Maximized;
            BackColor = Color.FromArgb(248, 250, 252);
            CargarIcono();

            _mensaje = new Label
            {
                Dock = DockStyle.Fill,
                TextAlign = ContentAlignment.MiddleCenter,
                Font = new Font("Segoe UI", 12F),
                ForeColor = Color.FromArgb(71, 85, 105),
                Text = "Abriendo el punto de venta...\n\n" +
                       "La primera vez del día tarda unos segundos.",
            };
            Controls.Add(_mensaje);

            Shown += AlMostrarse;
            FormClosing += AlCerrar;
        }

        private void CargarIcono()
        {
            try
            {
                string ico = Path.Combine(_carpetaBase, @"app\public\favicon.ico");

                if (File.Exists(ico))
                {
                    Icon = new Icon(ico);
                }
            }
            catch
            {
                // Sin icono propio se usa el de Windows; no es importante.
            }
        }

        private async void AlMostrarse(object emisor, EventArgs e)
        {
            string problema = _servidor.ComprobarInstalacion();

            if (problema != null)
            {
                MostrarError("La instalación está incompleta", problema);
                return;
            }

            try
            {
                _servidor.Iniciar();
            }
            catch (Exception ex)
            {
                MostrarError("No se pudo arrancar el programa", ex.Message);
                return;
            }

            bool listo = await EsperarServidor();

            if (!listo)
            {
                string detalle = _servidor.Salida;

                if (string.IsNullOrWhiteSpace(detalle))
                {
                    detalle = "PHP no escribió ningún mensaje.";
                }

                if (detalle.IndexOf("Failed to listen", StringComparison.OrdinalIgnoreCase) >= 0)
                {
                    detalle +=
                        "\n\nEl puerto " + _servidor.Puerto + " no estaba libre." +
                        "\n\nQué hacer:" +
                        "\n  1. Espere medio minuto y vuelva a abrir el programa." +
                        "\n     (si acaba de cerrarlo, Windows todavía no ha soltado" +
                        "\n      el puerto del todo)" +
                        "\n\n  2. Si sigue igual, abra con el Bloc de notas el archivo" +
                        "\n     puerto.txt de la carpeta del programa, escriba otro" +
                        "\n     número como 8348, guarde y vuelva a abrirlo.";
                }
                else
                {
                    detalle += "\n\nPara ver más detalles, ejecute " +
                               "\"Iniciar (modo diagnostico).bat\"\nen la carpeta del programa.";
                }

                MostrarError("El programa no alcanzó a iniciar", detalle);
                return;
            }

            await MostrarAplicacion();
        }

        private async Task<bool> EsperarServidor()
        {
            // Hasta 60 segundos: la primera arrancada tras instalar compila
            // la aplicación entera y llena la caché de PHP.
            for (int i = 0; i < 120; i++)
            {
                if (_servidor.Responde())
                {
                    return true;
                }

                // Si el proceso ya murió, esperar los 60 segundos completos
                // sólo hace perder el tiempo a quien está mirando.
                if (_servidor.Murio())
                {
                    return false;
                }

                await Task.Delay(500);
            }

            return false;
        }

        private async Task MostrarAplicacion()
        {
            try
            {
                _vista = new WebView2 { Dock = DockStyle.Fill };
                Controls.Add(_vista);

                // Los datos del navegador embebido van junto al programa, no
                // en el perfil del usuario: así la instalación es autónoma y
                // se puede borrar entera de una vez.
                var entorno = await CoreWebView2Environment.CreateAsync(
                    null, Path.Combine(_carpetaBase, "navegador"), null);

                await _vista.EnsureCoreWebView2Async(entorno);

                var nucleo = _vista.CoreWebView2;

                // Es un punto de venta, no un navegador: nada de menú
                // contextual, teclas de desarrollador ni ventanas emergentes.
                nucleo.Settings.AreDefaultContextMenusEnabled = false;
                nucleo.Settings.AreDevToolsEnabled = false;
                nucleo.Settings.IsStatusBarEnabled = false;
                nucleo.Settings.IsSwipeNavigationEnabled = false;

                // Una ventana nueva (el comprobante, por ejemplo) se abre
                // dentro de la misma, no en un navegador aparte.
                nucleo.NewWindowRequested += (s, a) =>
                {
                    a.Handled = true;
                    nucleo.Navigate(a.Uri);
                };

                nucleo.Navigate(_servidor.Url);

                Controls.Remove(_mensaje);
                _mensaje.Dispose();
                _mensaje = null;
            }
            catch (Exception ex)
            {
                // Lo más probable: falta el runtime de WebView2 (equipos con
                // Windows 10 sin actualizar). Se avisa y se abre en el
                // navegador, que siempre está.
                MostrarError(
                    "No se pudo abrir la ventana del programa",
                    ex.Message + "\n\n" +
                    "Se abrirá en el navegador. Para la ventana propia hay que\n" +
                    "instalar \"Microsoft Edge WebView2 Runtime\".",
                    true);
            }
        }

        private void MostrarError(string titulo, string detalle, bool abrirNavegador = false)
        {
            if (_mensaje != null)
            {
                Controls.Remove(_mensaje);
                _mensaje.Dispose();
                _mensaje = null;
            }

            var panel = new TableLayoutPanel
            {
                Dock = DockStyle.Fill,
                ColumnCount = 1,
                RowCount = 3,
                Padding = new Padding(30),
                BackColor = Color.White,
            };
            panel.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            panel.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
            panel.RowStyles.Add(new RowStyle(SizeType.AutoSize));

            panel.Controls.Add(new Label
            {
                Text = titulo,
                Font = new Font("Segoe UI", 15F, FontStyle.Bold),
                ForeColor = Color.FromArgb(185, 28, 28),
                AutoSize = true,
                Margin = new Padding(0, 0, 0, 14),
            });

            panel.Controls.Add(new TextBox
            {
                Text = detalle.Replace("\n", Environment.NewLine),
                Multiline = true,
                ReadOnly = true,
                ScrollBars = ScrollBars.Vertical,
                Dock = DockStyle.Fill,
                Font = new Font("Consolas", 9.5F),
                BackColor = Color.FromArgb(248, 250, 252),
                BorderStyle = BorderStyle.FixedSingle,
            });

            var pie = new FlowLayoutPanel
            {
                Dock = DockStyle.Fill,
                FlowDirection = FlowDirection.RightToLeft,
                AutoSize = true,
                Margin = new Padding(0, 14, 0, 0),
            };

            var cerrar = new Button
            {
                Text = "Cerrar",
                Size = new Size(120, 36),
                Font = new Font("Segoe UI", 10F),
            };
            cerrar.Click += (s, e) => Close();
            pie.Controls.Add(cerrar);

            if (abrirNavegador)
            {
                var boton = new Button
                {
                    Text = "Abrir en el navegador",
                    Size = new Size(190, 36),
                    Font = new Font("Segoe UI", 10F),
                };
                boton.Click += (s, e) =>
                {
                    try { Process.Start(_servidor.Url); } catch { }
                };
                pie.Controls.Add(boton);
            }

            panel.Controls.Add(pie);
            Controls.Add(panel);
        }

        private void AlCerrar(object emisor, FormClosingEventArgs e)
        {
            // El servidor muere con la ventana: si quedara suelto, el
            // siguiente arranque encontraría el puerto ocupado.
            _servidor.Detener();
        }
    }
}
