# Emparejado de productos con Odoo

Plan de trabajo. Escrito el 27-08-2026, después de revisar el módulo de
facturas, el catálogo de Odoo y el historial real de compras.

---

## El problema, en una frase

Las cotizaciones que el módulo de Solicitudes crea en Odoo van **sin
producto**, así que al confirmarlas Odoo no genera recepción y la mercadería
nunca entra al stock.

Comprobado con datos reales: la orden **P00222**, confirmada por una persona
(`state: purchase`), generó **0 recepciones**. Sus seis líneas tienen
`product_id = NULL`.

```
1. Confirmas la orden
2. Odoo crea la recepción         ← hoy NO ocurre, falta el product_id
3. Validas la recepción
4. Sube el stock
5. qty_received se llena → se puede facturar contra ella
```

El paso 4 sólo mueve inventario si el producto es **inventariable**
(`is_storable`). De los 2.361 productos de Odoo, lo son **1.517**. Un flete o
una mano de obra no deben mover stock aunque lleven producto.

---

## Lo que NO se toca

Tres límites acordados. No son preferencias: son la condición para trabajar
tranquilos.

| | Por qué |
|---|---|
| **Maestro de Odoo** (productos, proveedores) | Se administra allá. Este programa lo lee, jamás lo escribe. |
| **Base `fuelcontrol`** | Es el inventario real y las facturas procesadas. Impagable. |
| **Módulo de facturas / DTE** | Es el código que escribe en `fuelcontrol`. Se lee para aprender de él, no se modifica. |

Lo único que este programa escribe en Odoo es **la cotización de compra en
borrador** (`purchase.order`), que es lo que se pidió expresamente.

---

## Los datos que sostienen el diseño

Medidos sobre el Odoo de la empresa, no estimados.

**Las compras se repiten mucho.** De 1.273 líneas históricas hay 516 productos
distintos, y el **72 % de las líneas son de productos ya comprados antes**
(*Tuberías PVC riego 90/6*, doce veces). Un diccionario aprendido converge
rápido: el trabajo se hace al principio y después desaparece.

**Cada proveedor vende poquísimas cosas.** RODASERVIC: 3 órdenes, 10 líneas,
**8 productos distintos**. Buscar entre 8 es tratable; entre 2.347 es una
lotería. Éste es el dato más importante de todos.

**362 productos se compraron una sola vez.** Para ésos emparejar no se amortiza
nunca: hay que poder marcar la línea como «no va a inventario».

**Odoo ya tiene el mecanismo, sin usar.** `product.supplierinfo` guarda cómo
llama cada proveedor a cada producto (*Vendor Product Name* y *Vendor Product
Code*). Hay 267 filas cargadas, pero **con esos dos campos vacíos en las 267**:
sólo se usa para precios.

---

## Fase 1 — `odoo:sync-products`

**La primera patita. Es autónoma y útil aunque nunca se haga el resto.**

Un comando que copia el catálogo de productos de Odoo a una tabla local, igual
que ya hacen `odoo:sync-moves` y `odoo:sync-analytics`.

### Por qué una copia local y no consultar Odoo en vivo

- **Velocidad**: el fuzzy compara contra los 2.347 candidatos. Traerlos por
  JSON-RPC en cada emparejado es absurdo; en tabla local con índice es
  instantáneo.
- **Independencia**: si Odoo está lento o caído, el emparejado sigue con el
  catálogo de la última sincronización.
- **Se puede preguntar**: qué compró tal proveedor, qué productos no tienen
  equivalente en `fuelcontrol`, cuáles cambiaron de nombre. Por RPC eso es
  carísimo; en SQL es una consulta.

### Qué traer

De `product.product`:

| Campo | Para qué |
|---|---|
| `id` | Es lo que se manda en la línea de la cotización |
| `name` | Emparejado por texto |
| `default_code` | Código interno: cuando existe, manda sobre el nombre |
| `barcode` | Otro identificador exacto, gratis |
| `uom_id` | La unidad que Odoo espera |
| `type`, `is_storable` | Decide si mueve stock o no |
| `purchase_ok` | Filtrar lo que no se compra |
| `active` | Distinguir archivado de borrado |

De `product.supplierinfo` (267 filas): `partner_id`, `product_id`,
`product_name`, `product_code`, `price`. Hoy los nombres propios están vacíos,
pero la tabla existe y el día que se llenen es el mejor cruce posible.

### Lo que hay que cuidar

**El envejecimiento.** La copia local se desactualiza y alguien allá puede
renombrar, archivar o fusionar productos. La sincronización debe **marcar lo
que ya no está** —no borrarlo— para que un alias apuntando a un producto muerto
avise en vez de mandar a Odoo un `product_id` inválido.

Es el mismo cuidado que ya existe con `is_active` en `gmail_inventory_products`.

**Cada cuánto.** Una vez al día, de noche, como `odoo:sync-moves`. Los
productos no cambian a cada rato.

**Dónde vive.** Base `guias`, junto a las solicitudes. Ni `fuelcontrol` ni Odoo.

---

## Fase 2 — La tabla de alias

Una fila por *cómo llama cada proveedor a cada cosa*:

```
proveedor  | texto normalizado | producto fuelcontrol | producto Odoo | quién | cuándo
-----------+-------------------+----------------------+---------------+-------+-------
Unimarc    | jabon manos       | 86                   | 1234          | José  | 27-08
Líder      | jabon de manos    | 86                   | 1234          | Paola | 28-08
Sodimac    | jabon liq 500ml   | 86                   | (aún no)      | José  | 29-08
```

### Las dos decisiones de diseño

**Tabla, no JSON.** El módulo de facturas guarda sus alias en
`storage/app/gmail/product_aliases.json` (8 entradas hoy). Con ese tamaño
funciona; con 500 y dos personas confirmando a la vez, `file_put_contents` lee
el archivo entero, lo modifica en memoria y lo reescribe entero: **el segundo
pisa lo del primero**. Además un JSON no se puede consultar («¿cuántos alias
apuntan a un producto que ya no existe?») ni auditar («¿quién emparejó esto?»).

**La clave incluye al proveedor.** Hoy la del módulo de facturas es
`unidad|nombre`. Con el proveedor se gana lo importante: **buscar primero entre
lo que ese proveedor ya vendió** —8 candidatos, no 2.347— y sólo ampliar al
catálogo entero si no aparece.

### La regla que hace que valga la pena

**No se comparte la adivinanza; se comparte la decisión.**

El fuzzy es barato: una función que compara dos textos, 88 líneas, duplicarla
no duele. Y de hecho *deben* ser distintos: el umbral 0,88 que funciona contra
69 candidatos deja pasar falsos contra 2.347.

Lo caro es el minuto de criterio de la persona que dijo «esto es aquello». Eso
es lo que no se debe pedir dos veces, y por eso vive en una sola tabla.

### Cómo empezar sin tocar el módulo de facturas

1. La tabla nace en `guias`, la usa **sólo** el módulo de Solicitudes.
2. Se **siembra leyendo** los 8 alias del JSON existente. Lectura, sin escribir.
3. El módulo de facturas sigue exactamente igual.

Desde el día uno deja de duplicarse el trabajo *futuro*. Que facturas también
beba de esa tabla es una decisión aparte, y se haría contra una **copia de la
base**, nunca sobre la viva.

---

## Fase 3 — El emparejado en pantalla

Cascada de lo más seguro a lo más dudoso, tomando como referencia
`GmailDteInventoryService::resolveProductForIncomingLine`:

```
1. Alias aprendido (proveedor + texto)   → certeza
2. Código exacto (default_code, barcode) → certeza
3. Nombre exacto                          → certeza
4. Fuzzy entre lo que ese proveedor vendió antes  → SUGERENCIA
5. Fuzzy contra el catálogo completo               → SUGERENCIA
```

**Diferencia clave con el módulo de facturas**: allá el fuzzy **decide** (umbral
0,88 sobre 69 candidatos). Aquí, con 34 veces más candidatos, entre tantos
siempre habrá alguno que se parezca lo suficiente sin ser el correcto
—*MONOMANDO LAVATORIO ECO* contra *Monomando Lavamano* puntúa alto y son grifos
distintos—. Y un producto equivocado en una recepción **mueve stock real del
artículo que no era**.

Por eso: **el fuzzy ordena la lista, una persona elige, y lo que se guarda es
exacto.** A partir de la segunda vez ya no pregunta. Mismo patrón que ya
funciona con los proveedores («Vicat» → ARIDOS VICAT SUR SPA).

**Y una línea puede quedarse sin producto a propósito**: fletes, servicios,
compras irrepetibles. Marcado como decisión, no como olvido, que es lo que
pasa hoy.

### Lo que vale la pena copiar del módulo de facturas

`similarityScore` mezcla tres medidas en vez de fiarse de una:

```
similar_text 45 % + levenshtein 35 % + tokens comunes 20 %
```

Y tiene una idea que un puntaje ciego no tendría: **si las tallas difieren,
devuelve 0 de golpe**. XL contra XXL no es «parecido», es otro producto. Al
portarlo conviene extender esa idea a voltajes (12V ≠ 24V) y medidas (75 mm ≠
110 mm), que en este catálogo abundan.

---

## Decisiones pendientes — de operación, no de código

**Los 18 artículos que están en los dos inventarios.** De los 69 productos de
`fuelcontrol`, 18 tienen equivalente en Odoo; tres son idénticos (*Botellón 20
litros*, *NUTO H 68 208LT*, *Traje de agua verde PU XL*).

Hoy no hay doble conteo porque **los dos circuitos están desconectados a
propósito** —el módulo de facturas no lee ni escribe stock en Odoo— y porque los
EPP no se piden por Solicitudes. Pero si mañana se compra un botellón por
solicitud (entra a Odoo) y luego llega su factura (entra a `fuelcontrol`), el
mismo botellón queda contado dos veces.

No lo impide el sistema: lo impide que nadie apriete las dos puertas. **Hay que
decidir cuál manda para esos 18 artículos.** Sebastián lo conversará con el
jefe.

**Si el taller empieza a usar el módulo de facturas para su stock**, esa
decisión se vuelve urgente.

---

## Reglas existentes que conviene no romper

En `app/Console/Commands/GmailLeerXml.php`, tres exclusiones de combustible que
viven donde entra el documento, no donde se aprieta el botón:

1. **Ley 18.502 / vehículo** — busca `LEY 18.502` o `VEHICUL` en las referencias
2. **Patente excluida** — `vehiculos.excluye_stock`: cargado en bomba directo al
   estanque de un vehículo, se registra pero no suma al estanque propio
3. **Prepago / cupón** — `TermPagoGlosa` o sucursal `CUPON`: son litros de saldo
   en tarjeta, no entraron al estanque

**Punto ciego conocido**: las tres dependen de que el XML traiga el dato. La
patente sale de `<Transporte><Patente>`, que es **opcional** en el DTE. Un
proveedor que emita sin ella hace que la carga entre al estanque sin que nadie
se entere: no hay error, simplemente suma.

---

## Orden sugerido

1. **`odoo:sync-products`** — autónomo, no toca nada, útil por sí solo
2. **Tabla de alias** en `guias`, sembrada desde el JSON existente
3. **Emparejado en la pantalla** de la solicitud aprobada, con confirmación
4. **Marcar líneas que no van a inventario**
5. *(aparte, y sólo si se decide)* que el módulo de facturas lea la misma tabla,
   trabajando contra una copia de `fuelcontrol`
