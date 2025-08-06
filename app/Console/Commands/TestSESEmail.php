<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSESEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-ses {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test AWS SES email configuration';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info("Testing AWS SES email configuration...");
        $this->info("Sending test email to: {$email}");

        try {
            Mail::raw('Este es un email de prueba para verificar la configuración de AWS SES.', function($message) use ($email) {
                $message->to($email)
                        ->subject('Test AWS SES - Sistema de Gestión Minera');
            });

            $this->info("✅ Email enviado exitosamente!");
            $this->info("Revisa tu bandeja de entrada para confirmar la recepción.");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error al enviar email:");
            $this->error($e->getMessage());

            $this->info("\nPosibles soluciones:");
            $this->info("1. Verifica que AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY estén configurados");
            $this->info("2. Verifica que el email remitente esté verificado en SES");
            $this->info("3. Verifica que estés en la región correcta de AWS");
            $this->info("4. Si estás en sandbox, solo emails verificados pueden recibir emails");

            return 1;
        }
    }
}
