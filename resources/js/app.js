import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse'
import { initFuelChartModal } from './fuelcontrol-chart-modal';
import dtePurchaseReferences from './dte-purchase-references';
import dteOdooOrders from './dte-odoo-orders';

window.Alpine = Alpine;

Alpine.plugin(collapse);
Alpine.data('dtePurchaseReferences', dtePurchaseReferences);
Alpine.data('dteOdooOrders', dteOdooOrders);

// ✅ Store global (estado para modales)
Alpine.store('ui', {
    open: false,
    openView: false,
    selectedUser: null,
});

Alpine.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initFuelChartModal());
} else {
    initFuelChartModal();
}
