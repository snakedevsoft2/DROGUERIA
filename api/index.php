<?php

/*
|--------------------------------------------------------------------------
| Punto de entrada para Vercel
|--------------------------------------------------------------------------
|
| Vercel sólo ejecuta archivos PHP que estén dentro de /api, así que este
| archivo se limita a delegar en el front controller de Laravel. Toda la
| aplicación sigue arrancando desde public/index.php como siempre.
|
*/

require __DIR__.'/../public/index.php';
