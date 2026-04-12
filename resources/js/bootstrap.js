import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Alpine is bundled and managed by Livewire 4 — do not import separately.
// Register Alpine plugins or extensions via the livewire:init event:
// document.addEventListener('livewire:init', () => { window.Alpine.plugin(...) });
