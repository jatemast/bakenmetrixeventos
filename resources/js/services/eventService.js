import api from './api'; // Asumo que se exporta una instancia de axios configurada

const API_URL = '/events'; // Ruta base de la API de eventos

const eventService = {
    getAllEvents: () => {
        return api.get(API_URL);
    },
    getEventById: (id) => {
        return api.get(`${API_URL}/${id}`);
    },
    createEvent: (eventData) => {
        return api.post(API_URL, eventData);
    },
    updateEvent: (id, eventData) => {
        return api.put(`${API_URL}/${id}`, eventData);
    },
    deleteEvent: (id) => {
        return api.delete(`${API_URL}/${id}`);
    }
};

export default eventService;