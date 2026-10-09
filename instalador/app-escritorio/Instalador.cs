// ---------------------------------------------------------------------------
// Instalar-Drogueria.exe: el instalador en un solo archivo.
//
// Lleva por dentro, como recurso, el mismo paquete de la memoria USB
// (INSTALAR.bat, LEEME.txt y recursos\). Al abrirlo:
//
//   1. Windows pide permiso de administrador (lo exige el manifiesto), que es
//      lo que INSTALAR.bat conseguía relanzándose.
//   2. Descomprime el paquete en una carpeta temporal.
//   3. Ejecuta recursos\instalar.ps1 en esta misma consola y espera.
//   4. Borra la carpeta temporal.
//
// Así quien lo recibe no tiene que descomprimir nada: un doble clic y el
// asistente de siempre. Lo compila construir-exe.ps1 con el csc.exe de
// Windows, igual que Drogueria.exe.
// ---------------------------------------------------------------------------

using System;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Reflection;

static class Instalador
{
    static int Main()
    {
        try { Console.Title = "Instalar Droguería"; } catch { }

        string temporal = Path.Combine(Path.GetTempPath(), "Drogueria-Instalador-" + Guid.NewGuid().ToString("N").Substring(0, 8));

        try
        {
            Console.WriteLine();
            Console.WriteLine("  Preparando el instalador, un momento...");

            using (Stream recurso = Assembly.GetExecutingAssembly().GetManifestResourceStream("paquete.zip"))
            {
                if (recurso == null)
                {
                    return Fallar("El instalador está dañado: no trae el paquete del programa.");
                }

                using (var zip = new ZipArchive(recurso, ZipArchiveMode.Read))
                {
                    zip.ExtractToDirectory(temporal);
                }
            }

            string asistente = Path.Combine(temporal, "recursos", "instalar.ps1");
            if (!File.Exists(asistente))
            {
                return Fallar("El instalador está dañado: falta recursos\\instalar.ps1.");
            }

            // Ruta completa a powershell.exe: no depender del PATH del equipo.
            string powershell = Path.Combine(Environment.SystemDirectory, @"WindowsPowerShell\v1.0\powershell.exe");

            var inicio = new ProcessStartInfo
            {
                FileName = powershell,
                Arguments = "-NoProfile -ExecutionPolicy Bypass -File \"" + asistente + "\"",
                WorkingDirectory = temporal,
                UseShellExecute = false,   // misma consola: el asistente se ve aquí
            };

            using (var proceso = Process.Start(inicio))
            {
                proceso.WaitForExit();
                return proceso.ExitCode;
            }
        }
        catch (Exception ex)
        {
            return Fallar("No se pudo completar la instalación:\n  " + ex.Message);
        }
        finally
        {
            // Si queda algo bloqueado no importa: es la carpeta temporal de
            // Windows y se limpia sola con el tiempo.
            try { if (Directory.Exists(temporal)) Directory.Delete(temporal, true); } catch { }
        }
    }

    static int Fallar(string mensaje)
    {
        Console.WriteLine();
        Console.ForegroundColor = ConsoleColor.Red;
        Console.WriteLine("  X  " + mensaje);
        Console.ResetColor();
        Console.WriteLine();
        Console.WriteLine("  Pulse Enter para cerrar.");
        Console.ReadLine();
        return 1;
    }
}
