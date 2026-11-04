<?php

return [
    'portal' => 'Portal del operador',
    'requires_implementation' => 'Requiere implementación',
    'menu_button' => 'Menú',
    'role' => [
        'admin' => 'Administrador',
        'counter' => 'Ventas',
    ],
    'modality' => [
        'river' => 'Fluvial',
        'air' => 'Aérea',
        'mixed' => 'Mixta',
    ],
    'sales' => [
        'active' => 'Ventas web activas',
        'testing' => 'Ventas web en pruebas',
        'pending' => 'Ventas web en validación',
        'disconnected' => 'Ventas web no conectadas',
        'suspended' => 'Ventas web suspendidas',
    ],
    'section' => [
        'operation' => 'Operación',
        'finance' => 'Ventas y finanzas',
        'company' => 'Mi empresa',
        'growth' => 'Crecimiento',
        'help' => 'Ayuda',
    ],
    'menu' => [
        'home' => 'Inicio',
        'departures' => 'Salidas',
        'boarding' => 'Embarque',
        'dock' => 'Venta en muelle',
        'sales' => 'Ventas',
        'collections' => 'Cobros y conciliación',
        'refunds' => 'Reembolsos',
        'service_fee' => 'Cargo de servicio NavegaYA',
        'profile' => 'Ficha pública',
        'fleet' => 'Flota',
        'team' => 'Equipo',
        'online_payments' => 'Pagos en línea',
        'advertising' => 'Publicidad',
        'messages' => 'Mensajes con NavegaYA',
        'support' => 'Soporte',
    ],
    'account' => [
        'mine' => 'Mi cuenta',
        'password' => 'Cambiar contraseña',
        'logout' => 'Cerrar sesión',
    ],
    'preparation' => [
        'collections' => 'Esta pantalla va a reunir los cobros del pasajero y su conciliación. Todavía no hay una fuente única para marcar un pago como “por revisar”, así que no mostramos un conteo.',
        'refunds' => 'Los reembolsos se van a solicitar y resolver aquí. Hoy no existe un registro de reembolsos, así que no hay una lista ni un total.',
        'payments' => 'Aquí se va a conectar Mercado Pago de la empresa. Mientras no exista esa conexión, los pagos en línea quedan pendientes de implementación. Culqi sigue disponible para los pasajes hasta que Mercado Pago esté aprobado.',
    ],
];
