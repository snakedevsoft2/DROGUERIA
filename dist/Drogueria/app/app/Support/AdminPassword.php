<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

/**
 * Clave del dueño de la droguería.
 *
 * No hay usuarios ni sesiones de trabajo: cualquiera que esté frente al
 * mostrador puede vender y consultar. Lo que no puede es borrar inventario sin
 * esta clave, que es lo único irreversible del sistema.
 */
class AdminPassword
{
    /** Ajuste donde vive el hash. */
    public const KEY = 'admin.password';

    /** Clave de fábrica, para que la droguería no quede encerrada el primer día. */
    public const INICIAL = '1234';

    /** Hash guardado; la primera vez siembra el de la clave de fábrica. */
    public static function hash(): string
    {
        $hash = Setting::get(self::KEY);

        if (! $hash) {
            $hash = Hash::make(self::INICIAL);
            Setting::put(self::KEY, $hash);
        }

        return $hash;
    }

    public static function check(?string $password): bool
    {
        return $password !== null && $password !== '' && Hash::check($password, self::hash());
    }

    public static function set(string $password): void
    {
        Setting::put(self::KEY, Hash::make($password));
    }

    /** ¿Sigue puesta la de fábrica? Se avisa hasta que la cambien. */
    public static function isDefault(): bool
    {
        return Hash::check(self::INICIAL, self::hash());
    }
}
