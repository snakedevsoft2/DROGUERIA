<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajuste suelto guardado en la base, con la clave como identificador.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** Lee un ajuste; devuelve el valor por defecto si nunca se guardó. */
    public static function get(string $key, ?string $default = null): ?string
    {
        return static::find($key)?->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
