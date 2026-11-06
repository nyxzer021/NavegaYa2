# NavegaYA — Especificación del panel de empresa y de la página pública de la empresa
**Versión 1.0 · 05/11/2026 · Documento fuente (source of truth) para el desarrollo**

Este documento es la referencia de cada etapa. Si algo no está aquí, pregunta antes de inventarlo.

---

## 1. Decisiones de negocio (no reinterpretar)
| # | Decisión |
|---|---|
| D1 | NavegaYA gana **solo el cargo de servicio** que paga el viajero en la web (tarifa + cargo = total). No se cobra el 8% a la empresa en las ventas web. Los `commission_amount` históricos se conservan |
| D2 | Pagos web con **Mercado Pago Split Payments 1:1**: la tarifa va a la cuenta de la empresa y el cargo a NavegaYA (`marketplace_fee`). Sin liquidaciones para ventas web |
| D3 | Mercado Pago para **pasajes**. **Culqi se mantiene para las suscripciones de anunciantes**. Culqi para pasajes sigue activo hasta que Mercado Pago esté aprobado; luego se apaga con `PAYMENT_PROVIDER` |
| D4 | El modo manual de reservas (la empresa acepta o rechaza) va en una fase POSTERIOR |
| D5 | **Ventas presenciales** (terminal propio o agencia autorizada) = **sin cargo de servicio**. NavegaYA no cobra nada en ellas |
| D6 | La **empresa crea sus agencias autorizadas** (sin aprobación de NavegaYA). La comisión de la agencia la paga la empresa (% por agencia). Una agencia vende para una sola empresa |
| D7 | **Descuentos:** los crea el administrador de la empresa; el vendedor solo elige de la lista. El cargo web se calcula sobre el precio ya descontado |
| D8 | **Encomienda** ≤ 50 kg (tarifa por tipo). **Envío de carga** > 50 kg (por kg, descuenta de la capacidad de carga de la nave). El umbral es configurable |
| D9 | **Equipaje** incluido por defecto: fluvial 10 kg de mano + 20 kg en bodega; aéreo 10 kg. El exceso se cobra con la tarifa de exceso de la empresa y, si el total supera 50 kg, se registra como envío de carga. **Todos estos valores son editables**: el superadministrador fija los valores por defecto y cada empresa ajusta los suyos |
| D10 | **Modalidades:** Fluvial · Aéreo (avioneta) · Aéreo fluvial (hidroavión). La empresa las declara al registrarse y el superadministrador aprueba cada una. El panel solo muestra lo aprobado |
| D11 | **Opiniones con estrellas:** solo viajeros con un viaje completado con la empresa. Comentar fotos: cualquier usuario registrado. Todo pasa por moderación |
| D12 | No crear "Agencias" de NavegaYA ni "Liquidaciones" |

---

## 2. Menú lateral final
Orden fijo: nunca cambia según el estado de la empresa. Lo no disponible se muestra atenuado con 🔒 y un tooltip con el motivo.

| Grupo (color del ícono) | Opción | Ícono (Tabler/Lucide) | Ruta |
|---|---|---|---|
| — (`#BA7517`) | Inicio | layout-dashboard | `/empresa/panel` (existe) |
| **MI EMPRESA** (`#185FA5`) | Datos de la empresa | building | nueva |
| | Sedes y terminales | map-pin | nueva |
| | Pagos en línea | credit-card | nueva |
| **CANALES DE VENTA** (`#D85A30`) | Punto de venta | device-desktop-dollar | `/empresa/pos` (existe; antes "Venta en muelle") |
| | Agencias autorizadas | certificate | nueva |
| | Encomiendas | package | nueva (hoy hay carga en admin) |
| | Envío de carga | truck-delivery | nueva |
| | Cierre de caja | cash-register | nueva |
| **VENTAS Y FINANZAS** (`#3B6D11`) | Historial de ventas | receipt | `/empresa/ventas` (existe) |
| | Cobros y conciliación | wallet | nueva |
| | Reembolsos | receipt-refund | nueva |
| | Comisiones de agencias | coins | nueva |
| | Comprobantes | file-invoice | nueva (fase posterior: SUNAT) |
| | Reportes | chart-bar | nueva |
| **OPERACIÓN** (`#0F6E56`) | Flota | ship | `/empresa/flota` (existe) |
| | Rutas | route | nueva |
| | Programación de salidas | calendar-time | `/empresa/salidas` (existe) |
| | Tarifas y descuentos | tag | nueva |
| | Embarque y manifiestos | scan | `/empresa/despacho` (existe) |
| | Tripulación | steering-wheel | nueva |
| | Incidencias | alert-triangle | nueva |
| **USUARIOS Y PRIVILEGIOS** (`#534AB7`) | Usuarios | users | `/empresa/personal` (existe) |
| | Perfiles y permisos | shield-lock | nueva |
| | Auditoría | history | nueva (filtra `audit_logs` por empresa) |
| **CONTENIDO** (`#993556`) | Página de la empresa | world | `/empresa/ficha` (existe) |
| | Secciones de la página | layout-list | nueva |
| | Galería y opiniones | photo-heart | nueva |
| | Políticas de viaje | file-certificate | nueva |
| | Apariencia | palette | nueva |
| | Campañas publicitarias | speakerphone | `/empresa/publicidad` (existe) |
| **AYUDA** (`#5F5E5A`) | Mensajes con NavegaYA | message-circle | `/mensajes-empresas` (existe) |
| | Soporte | lifebuoy | `/centro-de-solicitudes` (existe) |
| | WhatsApp de atención | brand-whatsapp | nueva |

**Íconos:** un cuadrado de 26 px con fondo sólido del color del grupo, figura blanca de 15 px y radio de 7 px.

**Ítem activo:** fondo `rgba(255,255,255,.11)`, texto blanco y barra dorada de 3 px a la izquierda. Badges dorados solo con datos reales.

**Tarjeta de la empresa (arriba del menú):**
- logo o iniciales y nombre;
- modalidades aprobadas y **puerto base de la organización** (no del primer barco);
- chip con el estado de las ventas web.

**Barra superior (56 px, blanca):**
- ruta de navegación (Grupo › Opción);
- buscador "Buscar boleto, pasajero o salida" (Ctrl+K);
- notificaciones;
- **usuario arriba a la derecha** (avatar, nombre, perfil ▾ → Mi cuenta, Cambiar contraseña, Cerrar sesión).

**Móvil (< 1024 px):** menú cerrado; ☰ abre un panel deslizante con fondo oscuro; se cierra con ✕, al tocar fuera o con Escape.

---

## 3. Perfiles y permisos
| Perfil | Ve por defecto | Indispensable | Notas |
|---|---|---|---|
| Administrador | Todo | Todo | Debe existir al menos uno |
| Vendedor | Punto de venta · Encomiendas* · Envío de carga* · Mis ventas · Cierre de caja | Punto de venta | Asignado a una sede o agencia. *Solo si su sede recibe carga; un vendedor de agencia nunca tiene carga. Al iniciar sesión entra directo a Punto de venta. Ve solo sus ventas, salvo que el administrador le active "Ver todas las ventas" |
| Operador | Programación (ver) · Embarque y manifiestos · Flota (ver) · Tripulación · Incidencias | Embarque y manifiestos | |
| Finanzas | Historial de ventas · Cobros · Reembolsos · Comisiones de agencias · Comprobantes · Reportes | Historial de ventas | |

- El administrador puede activar o desactivar módulos por usuario (casillas), salvo los indispensables.
- **Permisos validados en el servidor** (Policies/Gates o middleware) y **aislamiento por empresa** en todas las consultas. Usar `role_user.organization_id`.

---

## 4. Modalidades (D10)
- **Registro:** casillas Fluvial / Aéreo / Aéreo fluvial, cada una con sus permisos:
  - fluvial: permiso de operación regional o nacional, número y vencimiento;
  - aérea: permiso MTC/DGAC y Certificado de Explotador (AOC) con su vencimiento.
- **Flota** muestra solo las pestañas aprobadas: Embarcaciones / Avionetas / Hidroaviones.
- **Términos según la modalidad:** zarpe/despegue, puerto/aeródromo o punto de acuatizaje, capitán/piloto.
- **Venta aérea:** pide el **peso del pasajero y del equipaje**; el manifiesto aéreo los suma.
- **Agregar una modalidad después:** "Solicitar nueva modalidad" → el superadministrador aprueba.

---

## 5. Inventario de plazas (embarcaciones)
- Cada lugar vendible es una **plaza**. Columnas nuevas (migración aditiva) en `vessel_seats`:
  - `space_type` (`seat` | `hammock` | `cabin_bed`);
  - `cabin_code` y `cabin_sold_as` (`whole` | `per_bed`).
- Se reutiliza `reservation_seats` con el índice único (`departure_seat_unique`), así no hay sobreventa.
- Elementos decorativos (pasillo, baño, escalera, cabina de mando, carga) en `vessel_layout_cells` o en un JSON en `vessels`.
- **Validaciones:**
  - plazas totales ≤ capacidad homologada;
  - chalecos ≥ plazas totales;
  - no se puede borrar una plaza que ya tiene venta futura.
- **Etapas del diseñador:**
  1. asientos (plano actual) + zona de hamacas por cupo + camarotes como bloques;
  2. editor visual en cuadrícula con decorativos y plantillas;
  3. ganchos de hamaca numerados y camarote por cama.
- Las tarifas se definen por **clase de plaza** (Asiento / Hamaca / Camarote).

---

## 6. Pantallas y formularios del panel
Patrón de cada pantalla:
- encabezado con título = nombre del menú, descripción de una línea y **botón principal arriba a la derecha**;
- contenido en una tarjeta blanca;
- campos a dos columnas cuando son cortos.

| Pantalla | Campos o contenido principal |
|---|---|
| Inicio | Saludo + periodo · Atención inmediata (solo si hay) · 4 KPI con variación (ingresos de la empresa, pasajes, ocupación, zarpes) · Ventas por canal · Próximas salidas · Estado de la flota · Rutas con mejor y peor ocupación · Primeros pasos para empresas nuevas |
| Datos de la empresa | RUC (solo lectura), razón social, nombre comercial, puerto o aeródromo base, dirección fiscal, ciudad · modalidades con su estado y documentos · representante legal (nombre, DNI, correo, teléfono) · documentos con vencimiento |
| Sedes y terminales | Nombre, tipo (muelle/oficina/aeródromo), dirección, ciudad, horario, ☑ recibe carga, ☑ visible en "Dónde comprar", estado |
| Pagos en línea | Estado de la conexión (No conectado → Autorización pendiente → En pruebas → Validación pendiente → Activo · Token por vencer · Requiere reconexión · Suspendido · Error) · botón "Conectar Mercado Pago" (OAuth) · medios de pago con su estado · historial de conexión. Nunca pedir claves |
| Punto de venta | Sede fija según el usuario · salida · espacio (asiento/hamaca/camarote; plano por cubiertas) · tipo de pasajero · nombres, documento, teléfono · (aéreo) peso del pasajero y del equipaje · equipaje en kg (si pasa del umbral → carga automática) · descuento de la lista · resumen (tarifa, descuento, cargo S/ 0, total) · medio de pago (efectivo/Yape/tarjeta POS, recibido, vuelto) · "Cobrar y emitir boleto" → boleto con QR. Buscar pasajero frecuente para autocompletar |
| Agencias autorizadas | Razón social, RUC, nombre comercial, dirección, ciudad, teléfono, correo, comisión %, rutas que puede vender, descuentos permitidos, código automático AG-XX-0001, vigencia, ☑ visible, estado · "Guardar y crear usuario" |
| Encomiendas (≤ 50 kg) | Remitente y destinatario (nombre, documento, teléfono), destino, salida, tipo (sobre/caja/bulto/otro), descripción, peso, bultos, valor declarado, ☑ frágil, costo por tipo (editable solo por el admin), pago (remitente/destinatario), medio · aviso de productos prohibidos · "Registrar e imprimir guía" |
| Envío de carga (> 50 kg) | Los mismos datos + peso y volumen, tarifa por kg de la ruta, validación contra la capacidad de carga de la salida · guía de carga |
| Cierre de caja | Resumen del turno por medio (efectivo/Yape/tarjeta), pasajes, encomiendas, carga, reembolsos · efectivo contado, diferencia, observación · historial (el admin ve todas las sedes) |
| Historial de ventas | Filtros: periodo, canal (Web/Terminal/Agencia), sede o agencia, modalidad, estado, búsqueda · columnas: boleto, fecha, pasajero, salida, canal, tarifa, descuento, cargo NavegaYA, total, pago, estado, ⋯ · exportar CSV |
| Cobros y conciliación | Pestañas: aprobados, pendientes, rechazados, reembolsados, contracargos · ID de Mercado Pago, total, tarifa MP, acreditado a la empresa, cargo NavegaYA, fecha de acreditación, conciliado o por conciliar |
| Reembolsos | Lista + nueva solicitud: boleto, motivo, monto total o parcial, detalle · estados: Solicitado/Aprobado/Rechazado/Pagado |
| Comisiones de agencias | Por agencia: boletos, tarifas vendidas, %, comisión, pagado, por pagar · registrar pago (monto, fecha, medio, N.° de operación, comprobante) |
| Reportes | Ventas por ruta, ocupación por salida, carga transportada, ventas por vendedor o agencia · exportar Excel |
| Flota | Pestañas según modalidad · tarjetas con su estado · **embarcación (3 pasos):** datos (nombre, tipo, matrícula DICAPI, puerto base, eslora, motor) → capacidad y espacios (asientos, hamacas, camarotes × camas, carga máxima en t, chalecos) + diseñar plano → documentos (certificados y vencimientos) · **aeronave:** matrícula OB-, modelo, avioneta/hidroavión, asientos, peso máximo de despegue, carga útil, equipaje máximo por pasajero, aeronavegabilidad, seguro · mantenimiento programado |
| Rutas | Tramo oficial (de los aprobados por el superadmin), escalas (puerto + minutos desde el origen), duración en h y min |
| Programación de salidas | Calendario/lista · ruta, unidad (solo habilitadas y sin mantenimiento), única o **recurrente** (días, hora, desde/hasta), hora de embarque, tarifa de la ruta o personalizada, capitán o piloto, tripulación, ☑ recibe carga, nota · vista previa ("Se crearán N salidas · el viajero web pagará S/ X") · reprogramar o cancelar con **motivo obligatorio** y aviso a los pasajeros |
| Tarifas y descuentos | Tarifa por ruta y clase (asiento/hamaca/camarote): adulto, niño, estudiante, adulto mayor, ☑ incluye alimentación, temporada · descuentos: nombre, % o monto, rutas, canales (web/terminal/agencia), vigencia, límite de usos, requiere documento · umbrales de equipaje y carga |
| Embarque y manifiestos | Salida de hoy · escanear QR o buscar por DNI/boleto · embarcados/pendientes · (aéreo) peso total frente al límite · descargar manifiesto de pasajeros y de carga (PDF) · cerrar embarque |
| Tripulación | Nombre, DNI, cargo (capitán/motorista/marinero/piloto/copiloto), licencia, vencimiento, teléfono |
| Incidencias | Salida, tipo (retraso/avería/clima/nivel del río/otro), gravedad, descripción, fotos, acción (registrar/reprogramar/cancelar) |
| Usuarios | Invitar: nombre, correo, perfil, sede o agencia (vendedor), módulos (casillas, los indispensables con 🔒), **avatar aleatorio** ("Otro avatar"/"Subir foto") · la persona crea su propia contraseña por correo · estados: Invitado/Activo/Desactivado |
| Perfiles y permisos | Matriz de módulos × perfiles (Ver/Gestionar), indispensables bloqueados, "Restablecer recomendados" |
| Auditoría | Fecha, usuario, acción, módulo, antes → después, IP · filtros y exportación · solo lectura |
| Página de la empresa | Logo (512×512, con botón de cámara) · portada (1920×480, punto focal) · descripción · horario · sitio web · contacto visible · **vista previa en vivo** · % de completitud |
| Secciones de la página | Lista arrastrable: activar, ordenar y renombrar las pestañas públicas. Salidas e Información son obligatorias |
| Galería y opiniones | Subir y ordenar fotos o álbumes · moderar comentarios · responder opiniones · reportar |
| Políticas de viaje | Plantilla prellenada (`docs/politicas-de-viaje-plantilla.md`) con valores editables · versión y fecha de vigencia · se acepta al comprar y se imprime en el boleto |
| Apariencia | Color principal y de acento (con presets) de las pestañas y botones públicos, "Restablecer NavegaYA" |
| Campañas publicitarias | Lo existente; bloqueado con "Solicitar activación" si el plan está pendiente |
| WhatsApp de atención | Número (+51 con normalización), mensaje inicial, horario, ☑ mostrar en la ficha y en las salidas, probar enlace |

---

## 7. Página pública de la empresa (`/empresas/{slug}`)
- **Cabecera:** portada a todo el ancho (140–180 px). Debajo, en la franja blanca, el **logo completo, sin taparse**: 72 px, radio de 16 px.
- **Datos principales:** nombre + chip "Verificada", modalidad y puerto, ★ promedio y número de opiniones verificadas.
- **Sellos automáticos (chips de color pastel):** DICAPI vigente, número de embarcaciones, rutas y horario.
- **Acciones:** "Ver salidas y comprar" (verde, principal), "WhatsApp" (dorado) y "Compartir".
- **Pestañas con ancla y su ícono de color sólido:**
  1. **Salidas y tarifas** `#salidas` (obligatoria);
  2. **Información** `#informacion` (obligatoria);
  3. Flota `#flota`;
  4. **Experiencias** `#experiencias` (galería + opiniones);
  5. Dónde comprar `#donde-comprar`;
  6. Encomiendas y carga `#carga`;
  7. Políticas de viaje `#politicas`.
- **Experiencias:**
  - promedio con barra de distribución;
  - galería con ❤ y comentarios;
  - "Cuenta tu experiencia" (estrellas obligatorias, mínimo 10 caracteres, fotos opcionales; solo viajeros verificados);
  - opiniones con el sello "Viaje verificado" y la respuesta de la empresa.
- **Móvil:** pestañas deslizables y botón "Comprar" fijo abajo.

### 7.1 Tarjeta de salida programada (diseño aprobado: "B compacta")
```
┌──────────────────────────────────────────────────────────────────────────────┐
│ [verde #064E3B, 30 px] 📅 Hoy, miércoles 5 nov               [Últimos lugares] │
│ 15:30        ──── 🚢 ────        20:10      [Asiento S/198][Hamaca S/132][Camarote S/440] │
│ Lagunas       4 h 40 min          Pebas                                         │
│ 🚢 ALBERTO I · 🛡 DICAPI vigente · 👥 26 libres                  [Elegir lugar →] │
└──────────────────────────────────────────────────────────────────────────────┘
```
- **Alto aproximado: 120 px.** Franja de fecha de 30 px en `#064E3B` (las salidas de otros días en `#085041`).
- **Horas:** 22 px/500. Ciudades y datos: 13 px en color de texto principal. Duración: 12.5 px secundario.
- **Pastillas de clase** (2 líneas: clase 12.5 px + precio 16 px/500):
  - asiento: fondo `#E1F5EE`, texto `#04342C`;
  - hamaca: `#E6F1FB` / `#042C53`;
  - camarote: `#FAEEDA` / `#412402`.
- **Etiqueta "Últimos lugares"** en `#F4B820` / `#412402` cuando quedan ≤ 30% de las plazas. "Libres" en ámbar `#854F0B` si son pocos y en verde `#0F6E56` si hay muchos.
- **Botón "Elegir lugar"** de 34 px en `#064E3B`. En móvil, las pastillas ocupan todo el ancho y el botón mide 38 px a ancho completo.
- **Precio mostrado:** en la web, el total con el cargo de servicio (precio final).

---

## 8A. Sincronización con el panel del superadministrador
**Principio:** la empresa y el superadministrador leen y escriben las **mismas tablas**. El admin no tiene copias: usa vistas globales (todas las empresas) y por empresa, con filtros. Toda acción sensible del admin exige un **motivo** y queda en la auditoría.

### 8A.1 Menú del superadministrador (misma estructura de grupos)
| Grupo | Opción del admin | Hoy existe como |
|---|---|---|
| — | Inicio · Bandeja de solicitudes | Resumen · Bandeja de solicitudes |
| EMPRESAS | Empresas y afiliaciones (modalidades, documentos, vencimientos, sedes) | Empresas y Afiliaciones |
| | Agencias autorizadas (todas) | **nueva** |
| | Conexiones de pago (Mercado Pago por empresa) | **nueva** |
| CANALES Y VENTAS | Ventas de pasajes (por canal: Web/Terminal/Agencia) | Ventas de pasajes |
| | Encomiendas y carga | Carga y Encomiendas |
| | Cobros y conciliación | **nueva** |
| | Reembolsos e incidencias de pago | Incidencias y Reembolsos |
| | Ingresos por cargo de servicio | Comisiones y Facturación (**renombrar**, D1) |
| | Reportes | **nueva** (hoy hay `admin.reports`) |
| OPERACIÓN | Flota por validar (unidades, plazas, certificados) | Flota por validar |
| | Puertos y aeródromos | Puertos y Aeródromos |
| | Rutas oficiales | Rutas y Corredores |
| | Supervisión de salidas | Supervisión de Itinerarios |
| MARKETPLACE | Negocios anunciantes · Categorías · Experiencias · Carrusel y campañas · Planes y suscripciones | Igual que hoy |
| CONTENIDO Y MODERACIÓN | Moderación (fotos, opiniones, reportes) · Políticas base (plantilla) · Menú y páginas del sitio | Moderación de contenido · Menú y páginas |
| SISTEMA | Usuarios y permisos · Configuración (cargo de servicio %, umbrales por defecto, WhatsApp público, pasarelas) · Auditoría | Usuarios y Permisos · Integraciones y Ajustes · Auditoría |
| AYUDA | Mensajes con empresas | Mensajes B2B |

Íconos de fondo sólido con los mismos colores de grupo que el panel de la empresa, para que ambos paneles se sientan el mismo producto.

### 8A.2 Qué hace la empresa ↔ qué ve y hace el superadministrador
| Panel de empresa | Superadministrador | Acciones del admin (con motivo) |
|---|---|---|
| Datos de la empresa · modalidades | Empresas y afiliaciones › ficha | Aprobar o rechazar **cada modalidad**; ver documentos y vencimientos; suspender la empresa |
| Sedes y terminales | Ficha de la empresa (pestaña Sedes) | Ver; ocultar de "Dónde comprar" ante un reporte |
| Pagos en línea | **Conexiones de pago** | Verificar la conexión (Validación pendiente → Activo); suspender pagos o ventas web |
| Punto de venta · Historial de ventas | Ventas de pasajes (global y por empresa) | Ver; exportar |
| Agencias autorizadas | **Agencias autorizadas** (todas) | Suspender una agencia por fraude o reclamos |
| Encomiendas · Envío de carga | Encomiendas y carga | Ver; atender reclamos |
| Cierre de caja | Ficha de la empresa (pestaña Caja), solo lectura | — |
| Cobros y conciliación | Cobros y conciliación global | Marcar excepciones; revisar diferencias |
| Reembolsos | Reembolsos e incidencias de pago | Aprobar **reembolsos excepcionales** |
| Comisiones de agencias | Solo lectura en la ficha de la empresa | — (es entre la empresa y su agencia) |
| (cargo de servicio web) | Ingresos por cargo de servicio | Configurar el % en Configuración |
| Flota (plazas, certificados) | Flota por validar | Aprobar o rechazar la unidad y su plano; suspender por certificado vencido |
| Rutas | Rutas oficiales | Crear o aprobar tramos oficiales |
| Programación · reprogramaciones | Supervisión de salidas | Pausar ventas de una salida o empresa |
| Tarifas y descuentos | Solo lectura | Fijar los umbrales por defecto (equipaje, carga) en Configuración |
| Embarque y manifiestos | Manifiestos (solo lectura; el embarque es del operador) | Descargar el manifiesto ante una autoridad |
| Tripulación | Ficha de la empresa (licencias y vencimientos) | Alertar por licencias vencidas |
| Incidencias | Supervisión de salidas / Incidencias | Ver y hacer seguimiento |
| Usuarios y perfiles | Usuarios y permisos (por empresa, para soporte) | Bloquear un usuario por seguridad |
| Auditoría (de su empresa) | Auditoría global | — |
| Página, galería y opiniones | Moderación | Aprobar, ocultar o quitar contenido |
| Políticas de viaje | Políticas base (plantilla que reciben las empresas) | Editar la plantilla base |
| Campañas publicitarias | Carrusel y campañas | Aprobar o rechazar las campañas |
| Mensajes · Soporte | Mensajes con empresas · Bandeja de solicitudes | Responder y cerrar |
| WhatsApp de atención | Ficha de la empresa | Ver |

### 8A.3 Bandeja de solicitudes del admin (todo lo que espera una decisión)
- modalidades por aprobar;
- unidades y planos por validar;
- conexiones de Mercado Pago por verificar;
- reembolsos excepcionales;
- campañas por aprobar;
- fotos y opiniones reportadas;
- documentos por vencer en ≤ 30 días.

Cada ítem lleva a su pantalla de decisión.

---

## 8. Sistema de diseño
- **Fuente:** Plus Jakarta Sans (400/500), con cifras tabulares en los números.
- **Paleta:**
  - verde `#064E3B` / `#087F5B` / `#0F6E56`;
  - oscuro del menú `#062C21`;
  - dorado `#F4B820`;
  - fondo `#F6F8F7`, tarjetas `#FFFFFF`, borde `#E6ECE9`;
  - texto `#10231D` / `#607069`.
- **Colores de grupo:** los de la sección 2.
- **Medidas:**
  - campos de 40 px (36 px en filtros) y botones de 36 px (40 px el principal);
  - casillas de 18 px;
  - radio de 8 px en controles, 12–16 px en tarjetas;
  - cuadrícula de 8 px;
  - tipografía 12.5 / 13.5 / 16 / 20 / 22–28.
- **Texto mínimo de 12.5 px**, contraste AA, foco visible.
- **Opciones tipo radio** como pastillas: la elegida con fondo verde claro y borde `#087F5B`.
- **Componentes Blade reutilizables** (`resources/views/components/ui/`): `page-header`, `card`, `kpi`, `button`, `input`, `select`, `textarea`, `checkbox`, `radio-pills`, `file-drop`, `badge`, `table`, `empty`, `avatar`, `tabs`, `toast`, `money-summary`, `departure-card`.
- **Sin emojis** en la interfaz. Un solo set de íconos (Tabler o Lucide).
- **Responsive:** 375 / 768 / 1024 / 1366 / 1920 px, sin scroll horizontal.
