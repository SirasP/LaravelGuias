import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse'
import { initFuelChartModal } from './fuelcontrol-chart-modal';
import dtePurchaseReferences from './dte-purchase-references';

window.Alpine = Alpine;

Alpine.plugin(collapse);
Alpine.data('dtePurchaseReferences', dtePurchaseReferences);

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
