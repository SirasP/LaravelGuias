# Web FuelControl por estanque

La web presenta ubicaciones distintas del mismo producto diésel mediante FuelTankViewData. No crea productos duplicados ni reasigna movimientos históricos. FUELCONTROL_ESTANQUES y FUELCONTROL_WEB_ESTANQUES deben estar activos para mostrar las ubicaciones. FUELCONTROL_PREVIEW permanece desactivado en producción.

Dashboard, Productos, Movimientos, Ingresos y Auditoría usan los estanques existentes. El dashboard incluye consumo por estanque, total de diésel, gasolina y los dos gráficos de vehículos. El total incluye históricos sin ubicación y cargas directas a vehículos; los gráficos de cada estanque muestran sus salidas aprobadas. Las fechas se presentan en español y los gráficos se amplían en un diálogo modal con bloqueo del fondo.

La auditoría usa el contexto cronológico completo de cada contador antes de filtrar las filas visibles. El Excel conserva el mismo filtro. Las capacidades físicas son opcionales y se configuran con FUELCONTROL_ESTANQUE_1_CAPACIDAD y FUELCONTROL_ESTANQUE_2_CAPACIDAD; sin datos reales no se calcula un porcentaje de llenado.

## Despliegue de una instalación que ya tiene estanques

1. Comparar las correcciones activas del servidor con el commit a desplegar. Respaldar archivos, configuración, assets y la base FuelControl fuera del directorio público.
2. Descargar el commit desde Git y compilar los assets con el lockfile del proyecto. Mantener los assets anteriores mientras existan páginas abiertas que puedan utilizarlos.
3. Actualizar por fast-forward, conservando cualquier archivo ajeno a este cambio. Activar únicamente FUELCONTROL_WEB_ESTANQUES=true en la configuración real y regenerar las cachés de configuración y vistas.
4. Verificar las vistas autenticadas, el catálogo móvil, filtros y auditoría. Comparar saldos, movimientos y operaciones antes y después.

Esta actualización de vistas no ejecuta inicialización, migraciones generales, importaciones ni copias de bases locales sobre producción. Los scripts de inicialización corresponden a la instalación inicial y no deben repetirse en una instalación activa.

## Validación

Pruebas relevantes: FuelConsumptionChartsTest, FuelTankServiceTest, FuelAuditHistoricalSequenceTest, FuelPendingQueueInboxTest y FuelSyncRejectionRecorderTest. La suite usa bases SQLite aisladas. Compilar assets con npm run build y plantillas con php artisan view:cache.
