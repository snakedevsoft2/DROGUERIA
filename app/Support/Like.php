<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Like
{
    /** PostgreSQL distingue mayúsculas en LIKE; 'ilike' no. SQLite ya las ignora. */
    public static function operator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
