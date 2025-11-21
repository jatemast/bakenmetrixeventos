import axios from 'axios';

const api = axios.create({
    baseURL: '/api', // Asumo que todas las rutas de la API comienzan con /api
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

export default api;