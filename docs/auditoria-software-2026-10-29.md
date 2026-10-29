# Auditoría funcional, UX y lógica de negocio de NavegaYA

Fecha: 29 de octubre de 2026

## Alcance revisado

- Panel del superadministrador y administradores con permisos limitados.
- Portal de empresas de transporte, incluyendo alta, flota, salidas, venta, embarque y publicidad.
- Portal de empresas anunciantes, suscripciones, ficha pública, campañas y soporte.
- Portal del viajero, reservas, carrito, pagos, boletos e historial de viajes.
- Separación de datos por empresa, permisos, estados de afiliación y sincronización con la base de datos.
- Compilación de vistas y pruebas automatizadas del sistema.

## Correcciones aplicadas

1. Se consolidó el acceso administrativo en el dashboard correcto y se respetan los permisos del administrador limitado.
2. Un administrador limitado ya no puede consultar mensajes B2B ni solicitudes de soporte sin el permiso de empresas.
3. Una empresa de transporte pendiente puede completar su flota y perfil, pero no puede vender, programar, embarcar ni publicar campañas antes de ser aprobada.
4. El menú empresarial oculta acciones operativas mientras la afiliación está pendiente y muestra el proceso de activación.
5. El carrito solo puede eliminar o cancelar reservas pertenecientes a la sesión actual.
6. El registro anunciante rechaza planes y ciudades desactivados; la edición también rechaza ciudades y categorías desactivadas.
7. La publicidad de transporte exige empresa activa, membresía activa y una ubicación incluida en los beneficios del plan.
8. Los paneles de empresa y anunciante recibieron ajustes adaptables para pantallas pequeñas.
9. Se añadió una prueba de humo que abre las páginas administrativas principales y detecta enlaces o vistas rotas.

## Coherencia de negocio verificada

- El superadministrador conserva control global; los administradores delegados ven únicamente módulos autorizados.
- Los datos operativos se relacionan con la organización y las acciones sensibles vuelven a validar esa propiedad en el servidor.
- La empresa pendiente prepara información, pero la aprobación administrativa habilita la operación comercial.
- Los beneficios publicitarios se toman de la instantánea de la suscripción para que un cambio posterior del catálogo no altere contratos vigentes.
- Las mejoras de plan aplican prorrateo y las reducciones se programan para el siguiente ciclo.
- Los anuncios pasan por revisión antes de publicarse y respetan límites y ubicaciones del plan.
- El viajero registrado ve reservas vinculadas a su usuario; una compra de invitado puede vincularse después de verificar el correo.
- La consulta de invitado exige documento y referencia de reserva o boleto.

## Resultado técnico

- 157 pruebas superadas.
- 951 aserciones superadas.
- Plantillas Blade compiladas correctamente.
- Formato PHP validado con Laravel Pint.
- `git diff --check` sin errores de espacios o parches corruptos.

## Riesgos y mejoras pendientes

### Prioridad alta antes de producción

1. Ejecutar el build de Vite en CI o Railway. En el equipo de auditoría Windows bloqueó el subproceso con `spawn EPERM`, por lo que ese control no quedó confirmado localmente.
2. Realizar una prueba de pago real en el entorno sandbox del proveedor, incluyendo webhook firmado, pago rechazado, reintento e idempotencia.
3. Añadir pruebas E2E en navegador para los recorridos completos: afiliación, aprobación, programación, compra, cambio de plan y publicación de anuncio.

### Prioridad media

1. Unificar el diseño de la página de perfil del usuario con el lenguaje visual de NavegaYA; todavía conserva la presentación base de autenticación.
2. Probar manualmente los paneles en móvil, tableta y resoluciones de escritorio con datos extensos, nombres largos y tablas llenas.
3. Añadir estados de carga y bloqueo de doble envío en formularios de pago, publicación y operaciones críticas.
4. Incorporar auditoría visible de cambios sensibles: permisos, tarifas, reprogramaciones, suscripciones y moderación.

### Operación y continuidad

1. Configurar copias de seguridad automáticas y probar restauración de la base de datos.
2. Configurar alertas para errores, webhooks fallidos, pagos inconsistentes y tareas en cola.
3. Definir indicadores operativos por rol y evitar mostrar métricas financieras a usuarios sin autorización.

## Conclusión

Los flujos principales están conectados a la misma base de datos y las reglas críticas cuentan con validación del servidor y pruebas automatizadas. No corresponde afirmar que un software es 100 % funcional solo con pruebas locales: antes de producción todavía se deben confirmar el build frontend, los pagos sandbox, los recorridos E2E y la operación de respaldo y monitoreo.
