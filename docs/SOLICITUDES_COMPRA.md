# Solicitudes de Compra EHE

Módulo interno para reemplazar el formulario en papel de solicitud de compra.
Un trabajador registra una necesidad, la guarda como borrador, la envía a
revisión y descarga el PDF corporativo. Compras la aprueba, la rechaza o la
devuelve para corrección, con historial completo.

**Alcance:** sólo la solicitud interna. No incluye cotizaciones de proveedores,
comparación de precios, adjudicación, orden de compra, recepción, factura ni
pago. Ver *Fuera de alcance* al final.

---

## 1. Instalación

```bash
php artisan migrate
php artisan db:seed --class=PurchaseRequestCatalogsSeeder
npm run build
```

El seeder crea el área `Administración` y las 12 unidades de medida tomadas de
los formularios reales. Centros de costo y lugares de entrega quedan vacíos a
propósito: los documentos revisados no los enumeran y el módulo no inventa
datos. Se cargan desde Configuración.

## 2. Almacenamiento privado

Los adjuntos se guardan en el disco `local` (`storage/app/private`), **nunca**
en `public`. No requieren `storage:link`; de hecho no deben tenerlo. Se
descargan sólo por `GET /solicitudes-compra/{id}/adjuntos/{adjunto}/descargar`,
que valida la policy antes de entregar el archivo.

Los PDF de cada revisión enviada se materializan una sola vez en
`storage/app/private/purchase-requests/{id}/revisions/{n}.pdf`.

Respaldo: incluir `storage/app/private/purchase-requests/` junto con la base de
datos. El PDF puede regenerarse desde el snapshot si se pierde el archivo, pero
el snapshot no puede regenerarse desde el PDF.

## 3. Avisos y correo

Los avisos salen por dos canales:

- **Dentro del sistema** (`database`), siempre. Se consultan en
  `/solicitudes-compra/avisos`, con contador de no leídos en la barra del
  módulo. Al abrir un aviso se marca leído y lleva a la solicitud.
- **Por correo**, si `PURCHASE_REQUESTS_MAIL=true` (por defecto) y la persona
  tiene dirección. Sale desde la cuenta configurada en `MAIL_*`.

Quién recibe qué:

| Momento | Recibe |
|---|---|
| Solicitud enviada o reenviada | Los `admin` activos, salvo quien la envió |
| Se pide anular una enviada | Los `admin` activos |
| Aprobada, rechazada, devuelta o anulada | El solicitante |

Nunca se notifica a proveedores.

### Tres cuidados que ya están resueltos

1. **El correo se envía fuera de la transacción** (`DB::afterCommit`). Enviarlo
   dentro dejaría filas bloqueadas mientras responde el SMTP, y un fallo de
   correo desharía la aprobación.
2. **Un fallo de correo no rompe nada.** El envío va en `try/catch` y sólo deja
   una advertencia en el log: la decisión ya está guardada y auditada, y la
   persona igual verá el aviso dentro del sistema.
3. **La suite jamás contacta un servidor SMTP.** `phpunit.xml` fuerza
   `MAIL_MAILER=array`, y hay una prueba que verifica esa configuración.

### Enviar a una dirección suelta

`Notification::route('mail', 'alguien@dominio.cl')->notify(...)` funciona: el
canal se decide con `routeNotificationFor('mail')`, no leyendo
`$notifiable->email`, que no existe en destinatarios anónimos. El saludo del
correo también tolera un destinatario sin nombre.

### APP_URL

Los correos construyen sus enlaces desde `APP_URL`. **En producción debe ser el
dominio real**: con `http://localhost` llegan enlaces que nadie puede abrir.

### Colas

Las notificaciones implementan `ShouldQueue`. Con `QUEUE_CONNECTION=sync` —el
valor actual— el correo se envía dentro de la misma petición, así que aprobar
tarda lo que tarde el SMTP (uno a tres segundos con Gmail). Para que la
respuesta sea inmediata:

```bash
php artisan queue:table && php artisan migrate
```

luego `QUEUE_CONNECTION=database` en el `.env` y un worker permanente
(`php artisan queue:work --queue=default --tries=3`) bajo supervisor o systemd.

## 4. Roles

Se montan sobre la columna `role` que el proyecto ya usaba. No hay tabla nueva.

| Rol | Valor en `users.role` | Puede |
|---|---|---|
| Solicitante | `viewer`, `bodeguero` | Crear, editar y enviar lo propio; corregir lo devuelto; anular su borrador; pedir anulación de lo enviado |
| Compras | `comprador` | Lo anterior, más **ver** todas las solicitudes, sus PDF y adjuntos. No decide |
| Administrador | `admin` | Único que aprueba, rechaza, devuelve y anula. Además mantiene catálogos y usuarios |
| Auditor | `auditor` | Sólo lectura de todas las solicitudes, PDF e historial |

**El administrador sí resuelve sus propias solicitudes.** Es una desviación
deliberada del criterio por defecto del documento de requisitos: en Agrícola EHE
es la única persona con atribución para decidir, y prohibírselo dejaría sus
propias solicitudes trabadas para siempre. La trazabilidad se mantiene intacta:
el historial registra quién decidió y cuándo.

Ningún otro rol puede decidir, ni sobre solicitudes ajenas ni sobre las propias.

### Reglas que la interfaz NO debe reimplementar

Las vistas preguntan a la policy (`$user->can('approve', $solicitud)`), nunca
reescriben la condición. Una versión anterior de `show.blade.php` calculaba
`auth()->user()?->role === 'admin' && auth()->id() !== $solicitud->user_id` por
su cuenta, y eso le ocultaba al administrador toda acción sobre sus propias
solicitudes aunque el backend las permitiera.

## 5. Máquina de estados

```
                 ┌──────────────── cancelled ◄──────────────┐
                 │                     ▲                    │
                 │                     │                    │
  draft ──────► submitted ─────► changes_requested ──► resubmitted
    │              │  │                                  │   │
    │              │  └──────────► approved ◄────────────┘   │
    │              └─────────────► rejected ◄────────────────┘
    └──────────────────────────► cancelled
```

| Desde | Puede pasar a |
|---|---|
| `draft` | `submitted`, `cancelled` |
| `submitted` | `approved`, `rejected`, `changes_requested`, `cancelled` |
| `changes_requested` | `resubmitted`, `cancelled` |
| `resubmitted` | `approved`, `rejected`, `changes_requested`, `cancelled` |
| `approved`, `rejected`, `cancelled` | — (terminales) |

Reglas: sólo `draft` y `changes_requested` se editan. Enviar exige al menos una
partida válida. Devolver, rechazar y anular exigen comentario. Cada reenvío
crea una revisión nueva y no destruye la anterior.

## 6. Modelo de datos

```
users ─┬─< purchase_requests ─┬─< purchase_request_items
       │                      ├─< purchase_request_revisions   (snapshot inmutable + PDF)
       │                      ├─< purchase_request_attachments (disco privado)
       │                      └─< purchase_request_events      (append-only)
       │
departments, cost_centers, locations, units_of_measure  (catálogos, slug canónico)
```

Decisiones que conviene conocer:

- **Folio** `SC-AAAA-NNNNNN`, emitido por el servidor dentro de la transacción.
  El formulario en papel usaba fecha + hora (`20260817 123113`), que no es una
  clave fiable.
- **Cantidades** en `decimal(18,6)`, nunca `float` ni entero. Se acepta coma o
  punto en la entrada y se normaliza en el servidor.
- **Snapshots**: `department`, `cost_center` y el nombre del solicitante se
  guardan como texto además del `*_id`, para que renombrar un catálogo no
  reescriba el historial.
- **`lock_version`**: concurrencia optimista. Dos revisores no pueden decidir
  sobre la misma versión.
- **Eventos y revisiones son inmutables** desde la aplicación: intentar
  modificarlos o borrarlos lanza `LogicException`.

## 7. Catálogos y el problema de "Admistración"

Los tres formularios reales escriben el mismo área de tres formas distintas:
`ADMINISTRACION`, `Admistración` y `Administracion`. El catálogo guarda un
`slug` canónico que absorbe tildes y mayúsculas, de modo que no puedan convivir
como áreas separadas.

El slug **no** corrige errores de tipeo: `Admistración` seguiría siendo un área
distinta. Eso se resuelve porque el área ahora se elige de una lista en vez de
escribirse a mano. La opción "Otra (especificar)" existe para no bloquear a
nadie mientras el catálogo se completa.

## 7 bis. Pantalla de catálogos

`/solicitudes-compra/catalogos`, sólo para `admin`. Administra las cuatro
listas: áreas, unidades de medida, centros de costo y lugares de entrega.

- **No se borra nada, se desactiva.** Una entrada desactivada deja de ofrecerse
  en solicitudes nuevas y las anteriores no cambian. Borrar rompería los
  informes aunque el nombre quede guardado como snapshot.
- **Rechaza variantes del mismo nombre.** Intentar crear `ADMINISTRACION`
  cuando ya existe `Administración` falla, porque ambas comparten el slug
  canónico. Si la equivalente está desactivada, el mensaje lo dice y pide
  activarla en vez de crear otra.
- Las unidades llevan además abreviatura y si admiten decimales.
- La ruta se declara **antes** del grupo de solicitudes: si no,
  `/solicitudes-compra/{purchaseRequest}` capturaría `catalogos` como si fuera
  el identificador de una solicitud. Hay una prueba que lo cubre.

## 8. PDF

Reproduce el formulario en papel: tipografía con serifas, marcos negros, grilla
de 23 renglones y bloque de proveedores al pie. Con más de 23 partidas continúa
en páginas nuevas repitiendo el encabezado de la tabla.

- Los renglones sobrantes van **en blanco**, nunca con ceros.
- Las cantidades se muestran con coma decimal y sin ceros de relleno (`1,5`,
  `295`).
- La tabla incluye una columna **Unidad** que el formulario original no tenía;
  en el papel la unidad iba incrustada dentro de la cantidad (`295 mtrs`,
  `20 C/ TALLA`). Es una mejora deliberada.
- Cada revisión enviada conserva su propio PDF y no se regenera con datos
  posteriores. Un borrador se previsualiza en vivo y sale marcado como tal.

**Logo:** si existe `public/img/logo-ehe.png`, se usa. Si no, la cabecera cae a
texto. No se dibuja un logo inventado.

## 9. Integración con Odoo

No implementada. Existe el puerto `PurchaseRequestExporter` enlazado a
`SimulatedPurchaseRequestExporter`, que no abre ninguna conexión. La suite
verifica con `Http::preventStrayRequests()` que no salga tráfico.

Cuando se implemente de verdad deberá: ejecutarse sólo tras una aprobación y
por acción explícita de Compras, crear a lo más una RFQ en borrador, ser
idempotente, no inventar proveedor ficticio, no crear productos desde textos
ambiguos y no escribir directo en la base de datos de Odoo.

## 10. Asistente por IA

Preparado y **apagado**. La interfaz `PurchaseRequestDrafter` está enlazada a
`NullPurchaseRequestDrafter`. El módulo funciona completo sin él.

Antes de encenderlo hay que decidir dónde corre el modelo: el VPS de producción
no tiene memoria para uno local. Y su salida debe tratarse siempre como
propuesta para que una persona revise, nunca como dato guardado: en las pruebas
locales, modelos de 2–3B inventaron cantidades y unidades que no estaban en el
texto.

## 9 bis. Marcar qué corregir

Al devolver una solicitud, el revisor no sólo escribe el motivo: marca casillas
sobre los puntos concretos que están mal. Puede señalar cualquier campo de la
cabecera (área, fecha requerida, motivo, centro de costo, proveedores,
adjuntos…) y cualquier partida por su número.

El solicitante ve esos puntos como etiquetas en el detalle y, al editar, los
campos y las tarjetas señaladas aparecen **resaltados y rotulados** («Compras
pidió corregir esto»). El color nunca va solo: siempre lo acompaña texto.

Detalles de implementación:

- El catálogo de puntos marcables vive en `App\Enums\PurchaseRequestCorrection`,
  en un solo lugar, para que el formulario del revisor, la validación y el
  resaltado no puedan desincronizarse. Marcar un punto inexistente se rechaza.
- `purchase_requests.requested_corrections` guarda lo pendiente y **se limpia
  al reenviar**: son correcciones vivas, no historial.
- El registro permanente va en el evento `changes_requested`, dentro de
  `metadata.corrections`, y por tanto es inmutable.
- Marcar puntos **no** reemplaza el comentario: sigue siendo obligatorio.
- En una aprobación o un rechazo las marcas se descartan; ahí no hay nada que
  corregir.
- El resaltado de partidas se congela al cargar el formulario: si el
  solicitante agrega o quita líneas, la numeración deja de corresponder y el
  marcado desaparece solo en vez de señalar la línea equivocada.

## 10 bis. Trampas conocidas

Tres fallos reales encontrados desde el navegador, con su prueba de regresión:

1. **`lock_version` llegaba como texto.** Un `<input type="hidden">` siempre
   envía string; la comparación estricta `!==` contra el entero del modelo la
   veía distinta y bloqueaba *toda* revisión con «La solicitud fue modificada
   por otra persona». Las pruebas no lo detectaban porque enviaban enteros. Hoy
   el Form Request castea la versión y hay un test que envía texto.
2. **La vista duplicaba la regla de permisos** y se desincronizó del backend.
   Ver arriba.
3. **El destino del formulario de revisión dependía de Alpine.** El `action` se
   reescribía en un `x-bind` que corre después del clic. Ahora cada botón lleva
   su propio `formaction`, que es HTML nativo y no tiene esa carrera.
4. **El contador «Por revisar» ignoraba las reenviadas.** Contaba sólo
   `submitted`, así que una solicitud corregida que volvía desaparecía de la
   bandeja justo cuando más urgía revisarla. Hoy existe el grupo
   `por_revisar` (`PurchaseRequestStatus::GROUP_AWAITING_REVIEW`), que reúne
   `submitted` + `resubmitted` en el contador, en el filtro y en la pestaña.

## 10 ter. Mensajes al usuario

La aplicación ya declaraba `APP_LOCALE=es`, pero sólo existía `lang/en`, así
que Laravel caía al fallback y mostraba los errores de validación en inglés.
Se agregó `lang/es/validation.php`, que corrige eso **en todo el proyecto**,
no sólo en este módulo.

Además, en las solicitudes:

- Los campos tienen nombres legibles y **numerados desde 1**: en vez de
  «suggested_suppliers.1» se lee «el proveedor sugerido N° 2», y en vez de
  «items.3.quantity», «la cantidad de la partida N° 4». Los índices se generan
  según lo que vino en la petición, para que señalen la línea correcta.
- Los proveedores repetidos se detectan sin distinguir mayúsculas y **sólo se
  marca la repetición**, no la primera aparición. La regla `distinct` de Laravel
  señalaba ambas, lo que hacía parecer culpable a la fila correcta.
- El toast reúne hasta tres errores en un solo aviso e indica cuántos quedan,
  en vez de mostrar sólo el primero. Su duración se ajusta al largo del texto
  (entre 4,5 y 14 segundos) para que dé tiempo a leerlo.
- El resumen de errores dentro del formulario se mantiene: el toast desaparece,
  y el documento de requisitos pide un resumen de errores accesible.

## 11. Pruebas

```bash
php artisan test tests/Feature/PurchaseRequests
```

44 pruebas. Corren sobre SQLite en memoria, aisladas de MySQL y de la conexión
`fuelcontrol` de producción.

## 12. Rollback

Las migraciones del módulo tienen `down()` completo:

```bash
php artisan migrate:rollback --step=4
```

Elimina catálogos, revisiones, adjuntos, eventos, partidas y solicitudes. **Es
destructivo**: respalda `storage/app/private/purchase-requests/` y la base
antes. Las rutas, vistas y clases pueden quedar en su sitio sin efecto: sin
tablas, el módulo simplemente no se usa.

## 13. Fuera de alcance (fase futura)

- Cotizaciones de proveedores y comparación de precios.
- Adjudicación y orden de compra.
- Recepción, factura y pago.
- Escritura real en Odoo.
- Aprobación por monto o por tramos jerárquicos.
- Múltiples empresas (la estructura ya lleva `company_code`).
- Asistente por IA conectado.
