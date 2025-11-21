import api from './api';

const API_URL = '/bonus-points'; // Ruta base de la API de puntos de bonificación

const bonusPointService = {
    getBonusPointsByUser: (userId) => {
        return api.get(`${API_URL}/user/${userId}`);
    },
    addBonusPoints: (userId, points) => {
        return api.post(`${API_URL}/user/${userId}/add`, { points });
    },
    subtractBonusPoints: (userId, points) => {
        return api.post(`${API_URL}/user/${userId}/subtract`, { points });
    },
    getBonusPointHistoryByUser: (userId) => {
        return api.get(`${API_URL}/history/user/${userId}`);
    }
};

export default bonusPointService;