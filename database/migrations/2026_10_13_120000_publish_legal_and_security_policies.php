<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updatedAt = now();

        $pages = [
            [
                'slug' => 'privacidad',
                'title' => 'Política de privacidad y protección de datos personales',
                'summary' => 'Explica qué datos trata NavegaYA, para qué los utiliza y cómo ejercer los derechos sobre ellos.',
                'content' => <<<'TEXT'
Última actualización: 13 de octubre de 2026

1. Responsable del tratamiento

NavegaYA es responsable del tratamiento de los datos personales recogidos mediante su plataforma de transporte fluvial y aéreo, sus formularios públicos y sus paneles de administración. Las consultas sobre privacidad y el ejercicio de derechos pueden presentarse mediante los canales oficiales de atención publicados en la plataforma.

2. Datos que tratamos

Podemos tratar datos de identificación y contacto de usuarios, pasajeros, representantes y personal de empresas; información de reservas, rutas, asientos, pagos y comprobantes; datos de afiliación y documentos remitidos por operadores; opiniones, reportes y contenido publicado; registros técnicos de acceso, dirección IP, dispositivo, actividad administrativa y eventos de seguridad.

NavegaYA no almacena el número completo, código de seguridad ni credenciales de una tarjeta. Cuando se habilitan pagos electrónicos, estos son procesados por el proveedor de pagos informado durante la compra.

3. Finalidades

Utilizamos los datos para crear y proteger cuentas; gestionar afiliaciones; buscar, reservar, pagar y emitir pasajes; identificar pasajeros y elaborar manifiestos; prestar soporte; prevenir fraude y abuso; moderar contenido; cumplir obligaciones contractuales y legales; mantener la seguridad y trazabilidad del sistema; y elaborar estadísticas operativas.

Las comunicaciones comerciales y las herramientas analíticas opcionales solo se habilitan cuando existe una base jurídica válida y, cuando corresponde, consentimiento previo.

4. Base del tratamiento

El tratamiento puede ser necesario para ejecutar una reserva o servicio solicitado, cumplir obligaciones legales aplicables al transporte y a la protección de datos, atender intereses legítimos de seguridad y prevención del fraude, o responder al consentimiento otorgado por la persona.

5. Destinatarios y encargados

Los datos se comparten únicamente cuando resulta necesario con la empresa transportista responsable del servicio, proveedores de alojamiento tecnológico, mensajería, soporte y pagos, autoridades competentes y otros encargados sujetos a obligaciones de confidencialidad y seguridad. No vendemos datos personales.

6. Conservación

Conservamos la información durante el tiempo necesario para prestar el servicio, atender reclamos, cumplir obligaciones legales, contables y de transporte, y defender derechos. Al concluir el plazo aplicable, los datos se eliminan, anonimizan o bloquean de forma segura.

7. Derechos de las personas

La persona titular puede solicitar información, acceso, actualización, rectificación, inclusión, oposición, supresión o cancelación de sus datos y, cuando corresponda, revocar el consentimiento o solicitar portabilidad. La solicitud debe permitir verificar la identidad del solicitante y describir el derecho que desea ejercer.

Si considera que su solicitud no fue atendida adecuadamente, puede acudir a la Autoridad Nacional de Protección de Datos Personales del Perú.

8. Seguridad e incidentes

Aplicamos controles de acceso por funciones, contraseñas cifradas, sesiones protegidas, registro de actividad administrativa, validación de entradas, copias de seguridad y medidas de seguridad en las comunicaciones. Ningún sistema es absolutamente infalible; por ello mantenemos un procedimiento de detección, contención, investigación, recuperación y notificación de incidentes.

9. Menores de edad

Los datos de pasajeros menores se utilizan exclusivamente para gestionar el viaje y deben ser proporcionados por su padre, madre, tutor o persona autorizada. NavegaYA no dirige publicidad basada en perfiles a menores.

10. Cambios

Podemos actualizar esta política cuando cambien el servicio, la tecnología o las obligaciones aplicables. La versión vigente mostrará su fecha de actualización.

Marco de referencia: Ley N.º 29733, Ley de Protección de Datos Personales, y su Reglamento aprobado por el Decreto Supremo N.º 016-2024-JUS.
TEXT,
            ],
            [
                'slug' => 'terminos',
                'title' => 'Términos y condiciones de uso',
                'summary' => 'Reglas aplicables al uso de NavegaYA, las reservas y la relación con las empresas transportistas.',
                'content' => <<<'TEXT'
Última actualización: 13 de octubre de 2026

1. Alcance del servicio

NavegaYA es una plataforma tecnológica que permite consultar rutas, comparar salidas, gestionar reservas y obtener pasajes ofrecidos por empresas transportistas. Cada empresa es responsable de la operación del viaje, sus unidades, permisos, horarios, condiciones de embarque y atención durante el transporte.

2. Información del usuario

El usuario debe proporcionar información verdadera, mantener la confidencialidad de su cuenta y revisar los datos del pasajero, ruta, fecha, hora, terminal y precio antes de confirmar una compra. No está permitido utilizar identidades ajenas, interferir con la plataforma, automatizar consultas abusivas ni realizar operaciones fraudulentas.

3. Reservas y pagos

Una reserva se confirma cuando el sistema registra correctamente el pago o la modalidad autorizada y emite la confirmación correspondiente. Los medios de pago disponibles se muestran antes de finalizar la operación. NavegaYA no solicita por mensaje contraseñas, claves bancarias ni códigos de seguridad.

4. Horarios, cambios y cancelaciones

Los horarios pueden variar por condiciones del río, clima, autorizaciones, mantenimiento, seguridad u otras circunstancias operativas. La empresa transportista debe comunicar las condiciones aplicables a cambios, reprogramaciones, cancelaciones y equipaje. NavegaYA registra y canaliza la información disponible, sin sustituir las decisiones de seguridad de la empresa o autoridad competente.

5. Embarque

El pasajero debe presentarse con anticipación, portar su documento y cumplir los controles de embarque. El boleto y su código QR son personales. La empresa puede impedir el embarque cuando exista riesgo para la seguridad, incumplimiento normativo o información inconsistente.

6. Contenido y opiniones

Las opiniones deben corresponder a experiencias reales y expresarse respetuosamente. Se prohíbe contenido ilegal, discriminatorio, amenazante, sexualmente explícito, fraudulento, invasivo de la privacidad o que suplante a terceros. NavegaYA puede ocultar contenido y suspender cuentas siguiendo su procedimiento de moderación, conservando la trazabilidad de la decisión.

7. Empresas afiliadas

Las empresas declaran que cuentan con facultades, permisos y documentación necesarios para operar. Son responsables de mantener actualizados sus datos, flota, capacidad, tarifas, itinerarios, políticas y contenido. NavegaYA puede revisar, observar, suspender o retirar una publicación por seguridad, incumplimiento o requerimiento de autoridad.

8. Propiedad intelectual

El software, identidad visual, estructura y contenido propio de NavegaYA están protegidos. El usuario o empresa que publica contenido declara contar con autorización para utilizarlo y concede a NavegaYA permiso limitado para mostrarlo dentro del servicio.

9. Disponibilidad y seguridad

Trabajamos para mantener la continuidad del servicio, pero pueden existir mantenimientos, fallas de proveedores o eventos fuera de control razonable. Informaremos incidentes relevantes por canales apropiados y restauraremos el servicio conforme a nuestro procedimiento de respuesta.

10. Contacto y legislación

Las consultas y reclamos pueden presentarse mediante los canales oficiales publicados en NavegaYA. Estos términos se interpretan conforme a la legislación peruana, sin limitar los derechos irrenunciables del consumidor.
TEXT,
            ],
            [
                'slug' => 'cookies',
                'title' => 'Política de cookies y tecnologías similares',
                'summary' => 'Describe las cookies necesarias y el consentimiento requerido para medición o publicidad.',
                'content' => <<<'TEXT'
Última actualización: 13 de octubre de 2026

1. Qué son las cookies

Las cookies son pequeños archivos que un sitio guarda en el navegador para recordar una sesión, una preferencia o una decisión. Tecnologías equivalentes pueden usar almacenamiento local o identificadores similares.

2. Cookies necesarias

NavegaYA utiliza cookies indispensables para mantener la sesión, proteger formularios contra solicitudes maliciosas, recordar el idioma y conservar funciones básicas del carrito. Estas cookies no se utilizan para crear perfiles publicitarios y no pueden desactivarse desde el panel de consentimiento porque la plataforma no funcionaría correctamente sin ellas.

3. Cookies analíticas

Si NavegaYA incorpora Google Analytics, Microsoft Clarity u otra herramienta de medición, no se activará antes de que el visitante acepte la categoría analítica. Su propósito será entender el uso agregado del servicio, detectar errores y mejorar la experiencia.

4. Cookies publicitarias

Las tecnologías destinadas a personalizar o medir publicidad requerirán consentimiento previo y separado cuando sean implementadas. Rechazar esta categoría no impedirá buscar rutas, reservar ni utilizar las funciones esenciales.

5. Gestión del consentimiento

El visitante puede aceptar, rechazar o cambiar sus preferencias cuando existan tecnologías opcionales. Retirar el consentimiento no afecta la licitud del tratamiento realizado anteriormente.

6. Configuración del navegador

También es posible eliminar o bloquear cookies desde el navegador. El bloqueo de cookies necesarias puede cerrar la sesión o impedir funciones de reserva y seguridad.

7. Cambios

Actualizaremos esta política cuando se añadan o retiren proveedores, finalidades o categorías de cookies.
TEXT,
            ],
            [
                'slug' => 'seguridad',
                'title' => 'Política pública de seguridad digital',
                'summary' => 'Principios con los que NavegaYA protege cuentas, operaciones, pagos y datos personales.',
                'content' => <<<'TEXT'
Última actualización: 13 de octubre de 2026

1. Compromiso

NavegaYA protege la confidencialidad, integridad, disponibilidad y trazabilidad de la información de pasajeros, usuarios, empresas y personal autorizado. Las medidas se revisan de acuerdo con los riesgos, el crecimiento del servicio y la normativa aplicable.

2. Controles principales

La plataforma aplica acceso basado en funciones; verificación de permisos; contraseñas cifradas; protección de sesiones y formularios; validación de archivos y entradas; limitación de solicitudes sensibles; encabezados de seguridad del navegador; auditoría de acciones administrativas; separación entre los paneles público, empresarial y de superadministración; copias de seguridad y actualización de dependencias.

3. Pagos

Los datos sensibles de tarjetas son introducidos en los componentes del proveedor de pagos habilitado. NavegaYA conserva referencias y estados necesarios para conciliar la operación, pero no el número completo ni el código de seguridad de la tarjeta.

4. Responsabilidades de los usuarios

Cada usuario debe utilizar una contraseña exclusiva, mantener seguros sus dispositivos, cerrar sesiones compartidas y desconfiar de mensajes que soliciten claves o códigos. NavegaYA nunca solicitará contraseñas o códigos bancarios por WhatsApp, correo o llamada.

5. Gestión de vulnerabilidades

Los reportes de posibles vulnerabilidades deben enviarse por un canal oficial, describiendo el problema sin acceder, modificar, descargar o divulgar datos de terceros. Investigaremos los reportes de buena fe y coordinaremos una corrección responsable.

6. Respuesta a incidentes

Ante un incidente, NavegaYA prioriza contener el acceso, preservar evidencias, determinar el alcance, corregir la causa, restaurar de forma segura y documentar las acciones. Cuando corresponda, notificará a las personas afectadas y a las autoridades mediante los canales y plazos aplicables.

7. Límites de divulgación

Por seguridad, esta política pública no revela configuraciones internas, credenciales, topología, reglas de detección ni detalles que faciliten ataques.
TEXT,
            ],
        ];

        foreach ($pages as $page) {
            DB::table('public_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                $page + ['is_published' => true, 'created_at' => $updatedAt, 'updated_at' => $updatedAt]
            );
        }
    }

    public function down(): void
    {
        DB::table('public_pages')->whereIn('slug', ['cookies', 'seguridad'])->delete();
    }
};
