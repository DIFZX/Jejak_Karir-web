using System;
using System.Diagnostics;
using System.IO;
using System.Threading;

internal static class Php81CgiLauncher
{
    private const string PhpCgi =
        @"C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php-cgi.exe";
    private const string PhpIniDirectory =
        @"C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64";

    public static int Main()
    {
        var startInfo = new ProcessStartInfo
        {
            FileName = PhpCgi,
            Arguments = "-c \"" + PhpIniDirectory + "\"",
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardInput = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true
        };

        startInfo.EnvironmentVariables["PHPRC"] = PhpIniDirectory;
        startInfo.EnvironmentVariables["PHP_INI_SCAN_DIR"] = string.Empty;

        using (var process = Process.Start(startInfo))
        {
            if (process == null)
            {
                return 1;
            }

            var input = new Thread(() =>
            {
                try
                {
                    Console.OpenStandardInput().CopyTo(process.StandardInput.BaseStream);
                }
                finally
                {
                    process.StandardInput.Close();
                }
            });
            var output = new Thread(() =>
                process.StandardOutput.BaseStream.CopyTo(Console.OpenStandardOutput()));
            var error = new Thread(() =>
                process.StandardError.BaseStream.CopyTo(Console.OpenStandardError()));

            input.Start();
            output.Start();
            error.Start();
            process.WaitForExit();
            input.Join();
            output.Join();
            error.Join();
            return process.ExitCode;
        }
    }
}
