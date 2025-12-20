import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Alpine from 'alpinejs';

// Make Alpine available before Livewire loads
window.Alpine = Alpine;

// Start Alpine only after Livewire is ready to prevent double initialization
document.addEventListener('livewire:init', () => {
    // Livewire will use the existing window.Alpine instance
});
