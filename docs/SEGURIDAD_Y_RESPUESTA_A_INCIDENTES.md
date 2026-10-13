# Política interna de seguridad y respuesta a incidentes

Versión 1.0 — 13 de octubre de 2026

## Objetivo y alcance

Esta política aplica a la aplicación NavegaYA, su base de datos, almacenamiento, integraciones, repositorios, infraestructura, cuentas administrativas y proveedores que intervienen en reservas, afiliaciones, pagos, contenido y soporte.

## Responsables

- El responsable del servicio coordina decisiones, comunicaciones y continuidad.
- El responsable técnico contiene, investiga, corrige y conserva evidencias.
- El responsable de privacidad evalúa el impacto sobre datos personales y las notificaciones requeridas.
- Los administradores y empresas afiliadas deben reportar inmediatamente cualquier anomalía y no investigar por cuenta propia alterando evidencias.

## Controles mínimos

1. Acceso por mínimo privilegio y revisión trimestral de permisos.
2. Autenticación multifactor para repositorio, alojamiento, base de datos, correo y proveedor de pagos cuando esté disponible.
3. Secretos únicamente en variables de entorno; nunca en código, capturas, tickets o chats.
4. HTTPS, cookies seguras en producción, protección CSRF, límites de solicitudes y encabezados de seguridad.
5. Copias de seguridad cifradas, prueba trimestral de restauración y separación de producción.
6. Registro de acciones administrativas, cambios de estado, accesos relevantes y eventos de pago.
7. Actualización mensual de dependencias y corrección prioritaria de vulnerabilidades críticas.
8. Validación de archivos, tipos MIME, tamaño y autorización antes de permitir su descarga.
9. Prohibición de utilizar datos reales en desarrollo salvo que estén anonimizados.
10. Contratos y revisión de proveedores con acceso a datos personales.

## Clasificación

- Crítico: control administrativo comprometido, extracción de datos, alteración de pagos o interrupción general.
- Alto: acceso no autorizado limitado, malware, exposición significativa o indisponibilidad relevante.
- Medio: intento contenido, vulnerabilidad explotable sin evidencia de datos afectados o afectación parcial.
- Bajo: evento informativo, error menor o intento bloqueado sin impacto.

## Respuesta

1. Detectar y registrar fecha, fuente, sistemas, personas y acciones iniciales.
2. Contener sin destruir registros: revocar sesiones, rotar secretos, aislar el componente y bloquear indicadores.
3. Preservar evidencias con control de acceso e integridad.
4. Determinar causa, datos afectados, personas impactadas, duración y alcance.
5. Corregir, probar y restaurar desde un estado confiable.
6. Evaluar notificaciones a personas, ANPD, Centro Nacional de Seguridad Digital, proveedores y otras autoridades.
7. Comunicar hechos confirmados, riesgos, medidas adoptadas y recomendaciones, evitando especulaciones.
8. Realizar una revisión posterior y asignar acciones con responsable y fecha.

## Canales oficiales

Los incidentes de seguridad digital pueden registrarse mediante el Formulario Virtual Único del Centro Nacional de Seguridad Digital. Los incidentes que involucren datos personales deben evaluarse también conforme a la Ley N.º 29733 y el Decreto Supremo N.º 016-2024-JUS.

## Pruebas y revisión

Se realizará al menos un ejercicio anual de respuesta, una prueba trimestral de restauración y una revisión de esta política cada año o después de un incidente significativo.
