import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Force JSON responses so Laravel returns 422 (not a 302 redirect) on validation errors.
window.axios.defaults.headers.common['Accept'] = 'application/json';
