# Prompt maestro — Web de Solicitudes de Compra EHE

## Fuente revisada

La ruta literal `odoo-playground/Solicitud EHE` no existía al momento de la revisión. Se usaron los tres formularios reales disponibles en `odoo-playground/Cotizaciones EHE`:

- `Solicitud de Compra 1.pdf`
- `Solicitud Compra Casas y Fosas.pdf`
- `Solicitud Compra Nueva Matriz 2026.pdf`

Los tres comparten estos datos: fecha de solicitud, número, fecha requerida, área/departamento, solicitante, motivo, productos o servicios, especificación, cantidad y proveedor sugerido opcional.

## Prompt listo para copiar

Actúa como arquitecto de software, diseñador UX y desarrollador senior de Laravel. Diseña e implementa dentro del proyecto existente `/Users/sebastianlopez/Developer/LaravelGuias` un módulo productivo llamado **Solicitudes de Compra EHE**.

El objetivo es reemplazar el formulario manual de solicitud de compra por una web interna, sencilla y responsive, donde un trabajador pueda ingresar una necesidad de compra, guardarla como borrador, enviarla a revisión y descargar un PDF con el formato corporativo. El responsable de compras debe poder revisarla, pedir correcciones, aprobarla o rechazarla, manteniendo un historial completo.

### 1. Contexto técnico existente

- Laravel 12.
- PHP 8.2 o superior.
- Blade, Alpine.js, Tailwind CSS y Vite.
- Laravel Breeze ya disponible para autenticación.
- Pest para pruebas.
- `barryvdh/laravel-dompdf` ya instalado para generar PDF.
- Interfaz en español de Chile.
- Zona horaria `America/Santiago`.
- Fechas visibles en formato `DD-MM-YYYY`.
- Mantén la arquitectura existente y no dañes funciones ajenas a este módulo.
- Reutiliza componentes, autenticación, layouts y estilos existentes cuando sean adecuados.
- No agregues una SPA ni otro framework frontend para este MVP.
- No agregues dependencias nuevas salvo que sean realmente necesarias y se justifiquen.
- Antes de editar, revisa el repositorio, sus instrucciones, el estado de Git y los cambios del usuario. Preserva todo cambio ajeno.

### 2. Alcance exacto del MVP

Implementa solamente el proceso de **solicitud interna de compra**:

1. Crear borrador.
2. Editar borrador.
3. Agregar, quitar y ordenar partidas dinámicamente.
4. Adjuntar antecedentes privados.
5. Revisar un resumen antes de enviar.
6. Enviar la solicitud.
7. Revisarla desde Compras o Jefatura.
8. Aprobar, rechazar o devolver para corrección con comentario obligatorio.
9. Consultar estado e historial.
10. Descargar un PDF corporativo versionado.

No implementes todavía cotizaciones de proveedores, comparación de precios, adjudicación, orden de compra, recepción, factura, pago ni escritura en Odoo. Diseña límites claros para que esas funciones puedan agregarse después sin mezclar ni sobrescribir la solicitud original.

### 3. Principios que no se pueden romper

- Una solicitud interna no es una cotización ni una orden de compra.
- El proveedor es opcional y sólo puede registrarse como sugerencia.
- No inventes proveedor, precio, impuesto, producto, unidad, fecha de entrega ni equivalencias.
- No se deben pedir precios ni impuestos en este formulario: los documentos reales no los contienen.
- La solicitud enviada no se elimina ni se sobrescribe silenciosamente.
- Toda devolución, aprobación, rechazo, cancelación o edición posterior al envío deja evidencia.
- No deduzcas que dos líneas repetidas son duplicadas; pueden corresponder a destinos diferentes.
- No conviertas automáticamente textos ambiguos en productos o unidades de inventario.
- Un doble clic o reintento no puede crear dos solicitudes ni dos folios.
- Ningún usuario debe ver o modificar solicitudes fuera de su permiso mediante URL manipulada.
- Los adjuntos son privados y nunca deben quedar publicados mediante una URL permanente.

### 4. Roles y permisos del MVP

Implementa roles en backend, no sólo ocultando botones:

#### Solicitante

- Crear solicitudes.
- Ver y editar sus propios borradores.
- Enviar un borrador.
- Ver sus solicitudes y su historial.
- Corregir únicamente solicitudes devueltas.
- Cancelar un borrador; después del envío, solicitar cancelación con motivo.

#### Revisor de Compras o Jefatura

- Ver solicitudes enviadas de las áreas autorizadas.
- Aprobar.
- Rechazar con motivo obligatorio.
- Devolver para cambios con comentario obligatorio.
- Descargar PDF y adjuntos si tiene permiso.

#### Administrador

- Administrar usuarios, roles, áreas/departamentos, ubicaciones, centros de costo y unidades de medida.
- Ver toda la auditoría.
- No alterar ni borrar eventos históricos.

#### Auditor o consulta

- Acceso de sólo lectura a solicitudes, PDFs e historial autorizados.

Por defecto, un solicitante no puede aprobar su propia solicitud. Si el sistema actual ya tiene una estrategia de roles, intégrate a ella sin duplicarla.

### 5. Estados y transiciones

Usa un enum y una máquina de estados explícita:

- `draft`: borrador editable.
- `submitted`: enviada y pendiente de revisión.
- `changes_requested`: devuelta para corrección.
- `resubmitted`: corregida y reenviada.
- `approved`: aprobada.
- `rejected`: rechazada con motivo.
- `cancelled`: anulada con motivo.

Reglas:

- Sólo `draft` y `changes_requested` se pueden editar.
- Enviar exige al menos una partida válida.
- Aprobar, rechazar o devolver exige autorización y una comprobación de versión para evitar carreras.
- Rechazar, devolver o cancelar exige comentario.
- Cada reenvío genera una revisión o snapshot; no destruye la versión anteriormente enviada.
- Toda transición registra actor, fecha, estado anterior, estado nuevo y comentario.

### 6. Datos de la solicitud

#### Cabecera

- ID interno ULID o UUID.
- Empresa, aunque inicialmente sólo exista Agrícola EHE SpA.
- Folio único generado en el servidor y dentro de una transacción, por ejemplo `SC-2026-000001`.
- No uses la hora del navegador como clave única.
- Fecha de solicitud, asignada por el servidor.
- Fecha requerida, obligatoria y no anterior a la fecha de solicitud.
- Área o departamento, desde catálogo normalizado. Sembrar `Administración` como dato inicial, evitando variantes como `ADMINISTRACION`, `Admistración` o `Administracion`.
- Solicitante formal, derivado del usuario autenticado y guardado también como snapshot histórico.
- Campo opcional `Solicitado para / solicitado por otra persona`. Es distinto del solicitante formal. Debe representar casos como “solicitud ingresada por Vanessa, pedida por Marco del Riego”.
- Motivo de la compra, obligatorio y multilínea.
- Prioridad `normal` o `urgente`; si es urgente, exigir justificación.
- Centro de costo, proyecto o destino, opcional en el MVP pero estructurado para futura integración analítica.
- Lugar de entrega o uso, opcional.
- Observaciones internas, opcionales.
- Cero a cuatro proveedores sugeridos, opcionales y sin adjudicación implícita.

#### Partidas dinámicas

Cada solicitud debe admitir una o muchas líneas y no quedar limitada a 20 o 23:

- Posición u orden.
- Producto o servicio, obligatorio.
- Especificación, opcional.
- Cantidad decimal positiva, con al menos tres decimales disponibles.
- Unidad de medida desde catálogo con opción “Otra”.
- Nota de cantidad o presentación opcional, por ejemplo `cada talla`, `paquete de 6`, `PAC=500 unidades`.
- Destino o uso específico opcional por línea.
- Se permite duplicar descripciones; no las fusiones automáticamente.

La interfaz y el backend deben soportar correctamente ejemplos reales como:

- `295` + `metros`.
- `2` + `metros`.
- `1,5` + `cubos`.
- `15` + `cada medida`.
- `20` + `cada talla`.
- `10` + `paquetes`, con presentación `6 unidades por paquete`.

Acepta coma o punto decimal en la entrada, normaliza en el servidor y conserva la precisión. No guardes la cantidad como entero ni como dinero.

#### Adjuntos

- Permitir PDF, JPG, JPEG y PNG.
- Validar extensión, MIME real, tamaño y nombre seguro.
- Guardar hash, tamaño, autor y fecha.
- Almacenar en disco privado.
- Descargar sólo mediante controlador autorizado o URL firmada de corta duración.
- No incrustar rutas internas en el HTML ni en el PDF.

### 7. Modelo de datos mínimo

Diseña migraciones, índices, relaciones, factories y modelos para:

- `purchase_requests`.
- `purchase_request_items`.
- `purchase_request_revisions`.
- `purchase_request_events` o `audit_events` append-only.
- `purchase_request_attachments`.
- `departments`.
- `cost_centers`.
- `locations`.
- `units_of_measure`.
- Roles y permisos, reutilizando el modelo existente cuando corresponda.

Campos técnicos importantes:

- `company_id` en toda entidad transaccional, aunque el MVP opere con una sola empresa.
- `requester_id` y snapshot del nombre del solicitante.
- `status` con enum.
- `revision_number`.
- `lock_version` para concurrencia optimista.
- `sort_order` en partidas.
- Cantidades como `decimal`, nunca `float`.
- Índice único por empresa, año y folio.
- Restricciones y claves foráneas reales; no dependas sólo de validación visual.
- No guardes listas de partidas o historial como un JSON opaco si necesitan búsqueda, relaciones o auditoría.

### 8. Experiencia móvil y de escritorio

#### Móvil, desde 360 px

- Una sola columna y sin desplazamiento horizontal.
- Botones y controles táctiles de al menos 44 px.
- Formulario por pasos cortos:
  1. Área, persona y fechas.
  2. Motivo y destino.
  3. Partidas.
  4. Adjuntos.
  5. Revisión y envío.
- Las partidas se muestran como tarjetas editables, no como una tabla comprimida.
- Botón claro “Agregar producto o servicio”.
- Guardar borrador visible y recuperación después de recargar.
- Validación junto al campo y resumen de errores accesible.
- Confirmación antes de enviar.

#### Escritorio

- Menú: Resumen, Mis solicitudes, Pendientes de revisión y Configuración según rol.
- Tabla con búsqueda y filtros por folio, estado, solicitante, área, fecha de solicitud y fecha requerida.
- Filtros en servidor sobre todos los registros, con paginación.
- Detalle con cabecera, partidas, adjuntos, revisión actual y línea de tiempo.
- Acciones visibles sólo cuando la transición esté permitida, pero autorización siempre validada también en backend.

#### Diseño visual

- Profesional, sobrio y agrícola.
- Fondo claro, texto de alto contraste y acentos azul/verde inspirados en Agrícola EHE.
- Usa el logo corporativo aprobado si existe como asset; no lo recrees ni inventes uno.
- Estados con texto e icono, nunca sólo con color.
- Evita exceso de gradientes, glassmorphism, animaciones o tarjetas decorativas.
- Objetivo WCAG 2.1 AA.

### 9. PDF corporativo

Genera un PDF tamaño carta con Dompdf que conserve la identidad del formulario analizado:

- Logo corporativo aprobado.
- Título `SOLICITUD DE COMPRA`.
- Fecha de solicitud.
- Folio.
- Fecha requerida.
- Área/departamento.
- Solicitante formal.
- Campo “Solicitado para / solicitado por”.
- Motivo.
- Tabla `N° | Producto / Servicio | Especificación | Cantidad | Unidad`.
- Proveedores sugeridos, si existen.
- Estado y número de revisión.
- Historial resumido de aprobación: responsable, decisión y fecha.
- Pie con fecha de generación y folio.

Reglas del PDF:

- No truncar descripciones ni partidas.
- Hasta 23 líneas deben caber razonablemente; si hay más, continuar en nuevas páginas repitiendo el encabezado de tabla.
- No generar filas fantasma con valores `0`.
- Las fechas se muestran en `DD-MM-YYYY`.
- La cantidad decimal se muestra sin perder precisión.
- Escapar todo texto ingresado por usuarios.
- El PDF de cada revisión enviada debe conservarse como snapshot; no regenerar una versión histórica con datos nuevos.
- El PDF sólo se descarga con autorización.

### 10. Auditoría y seguridad

- Usa Policies, Form Requests, middleware y autorización en servidor.
- CSRF, escape XSS, mass-assignment control, rate limiting en endpoints sensibles y sesiones seguras.
- Registra en historial: actor, rol, fecha, transición, comentario, revisión, campos modificados y adjuntos asociados.
- El historial es append-only desde la aplicación.
- No permitir `force delete` de solicitudes enviadas.
- Los logs no deben contener contraseñas, tokens ni archivos completos.
- Variables sensibles sólo en `.env`; nunca hardcodeadas.
- Agrega protección contra doble envío e idempotencia al emitir folio.
- Usa transacciones para crear solicitud, partidas, evento y snapshot.
- Prueba manipulación de IDs, acceso cruzado y carreras de aprobación.

### 11. Notificaciones

- Al enviar: notificar dentro del sistema al grupo revisor autorizado.
- Al devolver, aprobar o rechazar: notificar al solicitante.
- No notificar proveedores.
- No enviar correos externos en pruebas.
- Usa colas si habilitas correo interno y deja transportes externos simulados durante pruebas.

### 12. Preparación para Odoo, sin implementarla aún

Deja una interfaz o puerto de integración, pero usa solamente un adaptador simulado en este MVP.

La futura integración deberá:

- Ejecutarse sólo después de una aprobación y mediante una acción explícita de Compras.
- Crear como máximo una RFQ en estado borrador.
- No confirmar, enviar, recibir, facturar ni pagar automáticamente.
- Ser idempotente y guardar el vínculo local/Odoo.
- No utilizar un proveedor ficticio para representar la solicitud interna.
- No crear productos ni unidades a partir de textos ambiguos.
- No escribir directamente en la base de datos de Odoo.
- Conservar separados la solicitud original, las cotizaciones de proveedores y la RFQ u orden resultante.

No conectes este MVP a Odoo producción.

### 13. Pruebas obligatorias

Escribe pruebas Pest unitarias y feature para:

- Crear y editar un borrador.
- Normalizar coma decimal sin perder `1,5`.
- Una solicitud con 23 partidas.
- Partidas repetidas que permanecen separadas.
- Validación de fecha requerida.
- Envío idempotente y folio único bajo doble petición.
- Permisos del solicitante, revisor, administrador y auditor.
- Imposibilidad de aprobar la propia solicitud.
- Devolver con comentario y reenviar como revisión nueva.
- Rechazar y cancelar con motivo.
- Historial append-only.
- Concurrencia: dos revisores no pueden decidir estados incompatibles.
- Adjuntos privados, MIME inválido, exceso de tamaño y acceso no autorizado.
- Generación del PDF y contenido esencial.
- Más de 23 partidas paginadas sin pérdida.
- Filtros y paginación en servidor.
- Ninguna escritura o llamada real a Odoo.

Agrega al menos un flujo de navegador crítico para crear, enviar, revisar y descargar el PDF. No uses datos reales de trabajadores en seeders o capturas de prueba; usa personas ficticias.

### 14. Criterios de aceptación

No declares el trabajo terminado hasta demostrar lo siguiente:

1. Un usuario normal puede crear una solicitud completa desde un teléfono de 360 px sin scroll horizontal.
2. Puede guardar un borrador, cerrar, volver y continuar sin perder líneas.
3. Puede ingresar `1,5 cubos`, `295 metros` y `20 cada talla` de forma estructurada.
4. Puede enviar una solicitud de 23 líneas una sola vez aunque haga doble clic.
5. El revisor puede devolverla con motivo; el solicitante corrige y reenvía una revisión nueva.
6. El solicitante no puede aprobar su propia solicitud ni acceder a solicitudes ajenas.
7. El PDF reproduce todos los datos, partidas y revisiones sin truncar contenido.
8. Los adjuntos no son públicos y una URL manipulada devuelve acceso denegado.
9. La línea de tiempo muestra quién hizo cada acción y cuándo.
10. Build, migraciones, formato y toda la suite de pruebas pasan sin warnings ni errores.
11. La funcionalidad existente del proyecto sigue pasando sus pruebas.
12. No existe ninguna conexión ni escritura a Odoo producción.

### 15. Forma de trabajo y entregables

Trabaja en incrementos pequeños y verificables:

1. Audita el proyecto existente y documenta supuestos o conflictos.
2. Presenta un mapa breve de pantallas, modelo de datos, estados y matriz de permisos.
3. Implementa migraciones, enums, modelos, policies y servicios de dominio.
4. Implementa creación, borrador y envío.
5. Implementa revisión, devolución, aprobación y rechazo.
6. Implementa adjuntos, auditoría y PDF versionado.
7. Implementa dashboard, filtros y responsive final.
8. Agrega y ejecuta pruebas.
9. Revisa visualmente móvil y escritorio.
10. Entrega un informe final con archivos modificados, decisiones, pruebas ejecutadas, riesgos y pendientes.

Incluye como entregables:

- Diagrama o descripción ERD.
- Máquina de estados.
- Matriz de permisos.
- Migraciones y modelos.
- Form Requests, Policies, servicios, controladores y rutas.
- Vistas Blade/Alpine/Tailwind responsive.
- Plantilla PDF.
- Factories y seeders exclusivamente ficticios.
- Suite Pest.
- README de instalación, configuración, storage privado, colas, backup y rollback.
- Lista explícita de funciones dejadas para la fase futura.

Si encuentras una decisión que cambie materialmente el flujo —por ejemplo quién aprueba, si existe más de una empresa o si se necesita aprobación por monto— detente antes de implementarla, muestra la evidencia disponible y pide esa decisión. Para detalles menores, adopta una suposición conservadora, documéntala y continúa.

