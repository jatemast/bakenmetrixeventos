const dbPromise = idb.open('metrix-db', 1, upgradeDB => {
    upgradeDB.createObjectStore('sync-personas', { keyPath: 'id', autoIncrement: true });
});

async function savePersonaLocally(data) {
    const db = await dbPromise;
    const tx = db.transaction('sync-personas', 'readwrite');
    await tx.objectStore('sync-personas').add(data);
    return tx.complete;
}

async function syncPersonas() {
    const db = await dbPromise;
    const personas = await db.transaction('sync-personas').objectStore('sync-personas').getAll();
    
    for (const persona of personas) {
        try {
            const response = await fetch('/api/public/store-super-persona', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(persona)
            });
            if (response.ok) {
                const tx = db.transaction('sync-personas', 'readwrite');
                await tx.objectStore('sync-personas').delete(persona.id);
                console.log('Persona sincronizada:', persona.nombre);
            }
        } catch (e) {
            console.error('Error sincronizando, reintentando más tarde...');
            break; 
        }
    }
}

// Escuchar cambios en la red
window.addEventListener('online', syncPersonas);
