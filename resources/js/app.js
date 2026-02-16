import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse'

window.Alpine = Alpine;

Alpine.plugin(collapse);

// ✅ Store global (estado para modales)
Alpine.store('ui', {
    open: false,
    openView: false,
    selectedUser: null,
});

Alpine.start();