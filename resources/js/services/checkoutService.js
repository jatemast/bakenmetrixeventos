import api from './api';

const API_URL = '/events/checkout'; // Ruta base de la API de check-out

const checkoutService = {
    checkout: (checkoutCode, cedula) => {
        return api.post(API_URL, { checkout_code: checkoutCode, cedula });
    }
};

export default checkoutService;