# Factura y solicitud de compra

Implementación preparada y probada sobre una copia local del VPS el 28 de octubre de 2026.

## Comportamiento

La pestaña **Referencias cruzadas** relaciona partidas de facturas de proveedor
(DTE 33, 34 y 46) con partidas de las solicitudes `SC-…`. Se permiten varias
solicitudes por factura, varias facturas por solicitud y compras repartidas entre
proveedores. Relacionar documentos no aprueba solicitudes ni confirma, paga,
recibe o ingresa mercadería.

Estados:

- **Sin vínculo identificado**: todavía no hay una decisión; una búsqueda vacía
  nunca se interpreta como prueba de que no hubo solicitud.
- **Vinculación parcial**: quedan cantidades de factura por asignar o hay una
  relación que requiere revisión.
- **Solicitud vinculada**: todas las cantidades de las partidas de la factura
  tienen una relación vigente.
- **Compra sin solicitud**: un administrador confirmó la compra directa con un
  motivo. Puede volver a revisarla sin borrar el historial.

Las cantidades son las **vinculadas a facturas**, no lo recibido, pagado ni el
saldo del inventario. Se calculan con seis decimales exactos. Para cajas, packs
u otras unidades se solicitan las dos cantidades y una explicación; no se
infiere una conversión a partir de un nombre.

La consulta y las decisiones están restringidas al administrador, igual que las
cotizaciones. Bodegueros y otros roles no reciben datos de solicitudes ajenas a
través de los nuevos endpoints. La solicitud muestra las facturas relacionadas
al administrador, con sus partidas y enlaces de regreso.

## Sugerencias y confirmaciones

La búsqueda inicial exige evidencia del mismo RUT de proveedor en una
cotización asociada a esa partida y una similitud de producto suficiente. Es
una sugerencia para revisar, no una relación guardada ni prueba de adjudicación.
No se usan documentos cuyo cliente se identificó como otra empresa.

La búsqueda explícita por folio o motivo permite revisar otras solicitudes. Si
no hay evidencia del proveedor, la persona debe confirmar proveedor y
equivalencias y escribir el motivo. No se admiten solicitudes en borrador,
anuladas, rechazadas, de otra empresa o posteriores a la factura.

Cada envío lleva una clave de idempotencia. Los bloqueos por documento y partida
serializan las decisiones simultáneas. Una clave única de identidad de factura
impide repartir dos importaciones del mismo documento como si fueran compras
distintas. Se controla el saldo de la factura y el de la solicitud entre todas
sus facturas; no pueden excederse al vincular.

Las desvinculaciones conservan el registro original, quién decidió y el motivo.
Los cambios posteriores de factura, partida o solicitud señalan la relación para
revisión y conservan su cantidad reservada hasta resolverla.

## Origen automático en Odoo

El comando está apagado por defecto:

```dotenv
DTE_PURCHASE_REFERENCES_ODOO_SYNC=false
```

Utiliza las credenciales existentes `ODOO_*` **sólo para lecturas**. Requiere que
la configuración de solicitudes y la contable apunten al mismo URL y base de
Odoo, para no mezclar identificadores de QA y producción.

Para crear una relación exige:

1. Una factura contable local única por proveedor, folio, fecha y total.
2. Verificar de nuevo en Odoo RUT del proveedor, empresa compradora, tipo DTE,
   número, fecha, total, moneda CLP y estado contabilizado.
3. La relación real `account.move.line.purchase_line_id`, una orden confirmada
   del mismo proveedor y empresa, y su `origin` apuntando al folio SC exacto.
4. Una única partida local con los identificadores de orden y línea de compra.
   La solicitud debe estar aprobada o terminada.
5. Una correspondencia única entre línea XML y línea facturada en Odoo por
   descripción normalizada, cantidad, precio unitario y unidad; las cantidades y
   unidades de la orden y la solicitud también deben coincidir.

Si falta algo, no vincula. Las coincidencias repetidas, las conversiones de
presentación y los cambios de cantidades requieren revisión manual. Una
relación automática retirada por una persona no se recrea al sincronizar.

Las relaciones se revalidan al sincronizar. Una consulta interrumpida deja las
previas pendientes de comprobación; una evidencia de más de siete días también
se señala para revisión. El comando incluye las relaciones automáticas antiguas
además de las facturas recientes, para mantener esa comprobación.

```bash
# Primero simular un documento, con las lecturas habilitadas conscientemente.
php artisan dtes:sync-purchase-references --document=123 --dry-run

# Después de revisar los resultados, guardar los vínculos comprobados.
php artisan dtes:sync-purchase-references --limit=100
```

`--dry-run` ejecuta las mismas comprobaciones y revierte las escrituras locales.
Nunca cambia Odoo. Este proyecto usa cron directo; no se añadió un Schedule que
pareciera programado sin ejecutarse. Programar el nuevo comando en el VPS es un
paso de despliegue explícito, posterior a la revisión.

Antes de activar la lectura, comprobar la existencia de esos campos y que las
dos configuraciones usen la misma instancia. La disponibilidad de relaciones
automáticas depende de que se haya registrado el recorrido de compra en Odoo.
Cuando no exista, se requiere confirmación manual o completar ese recorrido.

## Datos y despliegue

Sólo se añaden tres tablas en la base principal:
`dte_purchase_decisions`, `dte_purchase_references`, `dte_purchase_events`.
No se altera ni escribe la base `fuelcontrol` al relacionar documentos. No hay
conexiones externas desde la consulta de la pestaña.

La migración específica:

```bash
php artisan migrate --path=database/migrations/2026_10_28_140000_create_dte_purchase_references.php --force
npm ci
npm run build
php artisan view:clear
```

La reversión de la migración elimina decisiones e historial, por lo que después
de usarse debe conservarse una copia antes de revertir. Para retirar una
relación operativa se usa **Desvincular**, que conserva el historial.

La copia local está en `LaravelGuias-dte-crossrefs`, con dos bases MySQL nuevas
`dte_crossrefs_guias_20261028` y `dte_crossrefs_fuel_20261028`. Tiene APP_KEY nuevo,
correo en log, integraciones sin credenciales y sin workers ni cron. Los dumps,
credenciales de prueba, evidencias y resultados están en `.local/`, ignorado por
Git. El archivo original de respaldo es `.local/backups/vps-20261028.sql`, con
permisos 600. No se copió el `.env` ni las claves de producción.

Los registros de demostración se identifican como **DEMO LOCAL**. Son datos
ficticios añadidos a la copia para probar reparto y facturación parcial; no se
deben exportar al VPS. Las pruebas de interfaz sobre documentos reales de la
copia local no deben guardar relaciones ficticias.

## Validación

```bash
php -d memory_limit=768M vendor/bin/pest --compact
node --test tests/Frontend/*.test.mjs
vendor/bin/pint --dirty --test
npm run build
```

Las pruebas PHP usan SQLite en memoria en las dos conexiones. La memoria
adicional es para la suite existente de PDFs. El correo de pruebas se fija en
`array`; no depende de la configuración de correo desactivado de la demo local.

Los casos cubren permisos, reparto por proveedor, factura con varias
solicitudes, cantidades parciales y decimales, importaciones duplicadas,
idempotencia, cajas/unidades, decisiones sin solicitud, datos modificados,
auditoría, procedencia automática, separación QA/producción, revalidación y
simulación sin persistencia. La interfaz se comprobó también en el navegador
contra MySQL local, incluyendo confirmar una segunda factura parcial ficticia.
