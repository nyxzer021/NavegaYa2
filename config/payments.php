<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pago de prueba
    |--------------------------------------------------------------------------
    |
    | Este modo confirma reservas sin efectuar un cargo. Debe ser false antes
    | de habilitar una pasarela real en producción.
    |
    */
    'sandbox_enabled' => (bool) env('PAYMENTS_SANDBOX', true),
];
