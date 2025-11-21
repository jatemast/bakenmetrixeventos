import api from './api';

const API_URL = '/events/checkin'; // Ruta base de la API de check-in

const checkinService = {
    checkin: (checkinCode, cedula, referral_code = null) => {
        return api.post(API_URL, { checkin_code: checkinCode, cedula, referral_code });
    }
    // getAttendeesByEvent no parece tener una contraparte en el backend para este flujo de check-in/out,
    // se podría revisar si es necesario para otra funcionalidad o eliminarla.
};

export default checkinService;