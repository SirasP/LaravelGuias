import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// ✅ Store global (estado para modales)
Alpine.store('ui', {
    open: false,
    openView: false,
    selectedUser: null,
});

Alpine.start();