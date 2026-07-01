<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;

class StrongPassword implements Rule
{
    public const MIN_LENGTH = 12;

    private $message = 'La contraseña no cumple los requisitos de seguridad.';

    private const COMMON_PASSWORDS = [
        'password',
        'password1',
        'password12',
        'password123',
        '12345678',
        '123456789',
        '1234567890',
        'qwerty123',
        'admin123',
        'admin1234',
        'changeme',
        'changeme123',
        'welcome123',
        'letmein123',
        'abc123456',
        'test123456',
    ];

    public function passes($attribute, $value): bool
    {
        if (!is_string($value)) {
            $this->message = 'La contraseña debe ser una cadena de texto.';

            return false;
        }

        if (strlen($value) < self::MIN_LENGTH) {
            $this->message = 'La contraseña debe tener al menos '.self::MIN_LENGTH.' caracteres.';

            return false;
        }

        if (!preg_match('/[a-z]/', $value)) {
            $this->message = 'La contraseña debe incluir al menos una letra minúscula.';

            return false;
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $this->message = 'La contraseña debe incluir al menos una letra mayúscula.';

            return false;
        }

        if (!preg_match('/\d/', $value)) {
            $this->message = 'La contraseña debe incluir al menos un número.';

            return false;
        }

        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->message = 'La contraseña debe incluir al menos un carácter especial.';

            return false;
        }

        if (in_array(strtolower($value), self::COMMON_PASSWORDS, true)) {
            $this->message = 'La contraseña es demasiado común. Elige una más segura.';

            return false;
        }

        if (!$this->isNotCompromised($value)) {
            $this->message = 'Esta contraseña aparece en filtraciones de datos conocidas. Elige otra.';

            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->message;
    }

    private function isNotCompromised($password)
    {
        try {
            $hash = strtoupper(sha1($password));
            $prefix = substr($hash, 0, 5);
            $suffix = substr($hash, 5);

            $response = Http::timeout(3)
                ->withHeaders(['Add-Padding' => 'true'])
                ->get("https://api.pwnedpasswords.com/range/{$prefix}");

            if (!$response->successful()) {
                return true;
            }

            foreach (explode("\n", $response->body()) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                [$hashSuffix] = explode(':', $line);

                if (hash_equals($suffix, $hashSuffix)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return true;
        }
    }
}
