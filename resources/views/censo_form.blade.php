<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Censo Oficial - Metrix</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        :root {
            --bg-color: #090b14;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.06);
            --primary: #6366f1;
            --secondary: #a855f7;
            --accent: #10b981;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15), transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.15), transparent 40%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 550px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }

        /* Progress Bar */
        .progress-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.05);
        }

        .progress-bar {
            height: 100%;
            width: 33%;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Step Layout */
        .form-step {
            display: none;
            animation: slideIn 0.5s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        }

        .form-step.active {
            display: block;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: #818cf8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.825rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 1.1rem;
            color: var(--text-muted);
            font-size: 1rem;
            transition: color 0.3s;
        }

        .form-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 0.85rem 1rem 0.85rem 2.85rem;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.35);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .form-input:focus + i {
            color: var(--primary);
        }

        #map {
            height: 200px;
            width: 100%;
            border-radius: 14px;
            margin: 1rem 0;
            border: 1px solid var(--glass-border);
            z-index: 5;
        }

        .row {
            display: flex;
            gap: 1rem;
        }

        .col {
            flex: 1;
        }

        @media (max-width: 480px) {
            .row { flex-direction: column; gap: 0; }
        }

        /* Checkbox & Conditional cards */
        .card-option {
            background: rgba(255, 255, 255, 0.02);
            padding: 1rem;
            border-radius: 14px;
            border: 1px solid var(--glass-border);
            margin-top: 1rem;
            transition: all 0.3s;
            cursor: pointer;
        }

        .card-option:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .card-option-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }

        .card-option-header input {
            cursor: pointer;
            accent-color: var(--primary);
            width: 18px;
            height: 18px;
        }

        .conditional-fields {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed rgba(255,255,255,0.08);
            display: none;
            animation: expandDown 0.3s ease forwards;
        }

        @keyframes expandDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Buttons Navigation */
        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 1rem;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-main);
            border: 1px solid var(--glass-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Notification */
        #notification {
            position: fixed;
            top: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: rgba(16, 185, 129, 0.9);
            backdrop-filter: blur(10px);
            color: white;
            padding: 0.85rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            transition: transform 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28);
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #notification.show { transform: translateX(-50%) translateY(0); }
        #notification.error { background: rgba(239, 68, 68, 0.9); }

    </style>
</head>
<body>

    <div id="notification">
        <i class="fa-solid fa-circle-check"></i>
        <span id="notify-msg">Éxito</span>
    </div>

    <div class="container" id="form-container">
        <div class="progress-container">
            <div class="progress-bar" id="progress"></div>
        </div>

        <div class="header">
            <h1>Censo Oficial</h1>
            <p>Registra tus datos de forma rápida y segura para el CRM</p>
        </div>

        <form id="censoForm">
            <!-- STEP 1: Datos Personales -->
            <div class="form-step active" id="step-1">
                <div class="section-title"><i class="fa-solid fa-user-circle"></i> Datos Personales</div>
                
                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">CURP <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="curp" class="form-input" placeholder="CURP (18 dígitos)" required maxlength="18">
                            <i class="fa-solid fa-id-card"></i>
                        </div>
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Cédula / Identificación</label>
                        <div class="input-wrapper">
                            <input type="text" id="identification_number" class="form-input" placeholder="Opcional">
                            <i class="fa-solid fa-id-badge"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Nombre(s)</label>
                    <div class="input-wrapper">
                        <input type="text" id="name" class="form-input" placeholder="Nombre completo" required>
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">Ap. Paterno</label>
                        <div class="input-wrapper">
                            <input type="text" id="last_name" class="form-input" placeholder="Paterno" required>
                            <i class="fa-solid fa-signature"></i>
                        </div>
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Ap. Materno</label>
                        <div class="input-wrapper">
                            <input type="text" id="maternal_name" class="form-input" placeholder="Materno">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">Edad</label>
                        <div class="input-wrapper">
                            <input type="number" id="age" class="form-input" placeholder="Años" required min="0">
                            <i class="fa-solid fa-calendar"></i>
                        </div>
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Género</label>
                        <select id="gender" class="form-input" style="padding-left: 1rem;" required onchange="togglePregnancy()">
                            <option value="H">Hombre</option>
                            <option value="M">Mujer</option>
                            <option value="O">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="card-option hidden" id="preg-card">
                    <div class="card-option-header" onclick="document.getElementById('is_pregnant').click()">
                        <input type="checkbox" id="is_pregnant">
                        <label>¿Se encuentra embarazada?</label>
                    </div>
                </div>

                <div class="nav-buttons">
                    <button type="button" class="btn btn-primary" onclick="nextStep(2)">Siguiente <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- STEP 2: Ubicación -->
            <div class="form-step" id="step-2">
                <div class="section-title"><i class="fa-solid fa-location-dot"></i> Ubicación</div>

                <div class="form-group">
                    <label class="form-label">Calle</label>
                    <div class="input-wrapper">
                        <input type="text" id="street" class="form-input" placeholder="Nombre de la calle" required>
                        <i class="fa-solid fa-map-pin"></i>
                    </div>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">N. Exterior</label>
                        <input type="text" id="external_number" class="form-input" style="padding-left: 1rem;" placeholder="# Ext">
                    </div>
                    <div class="col form-group">
                        <label class="form-label">N. Interior</label>
                        <input type="text" id="internal_number" class="form-input" style="padding-left: 1rem;" placeholder="Apt / Piso">
                    </div>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">C.Postal <span class="required">*</span></label>
                        <input type="text" id="postal_code" class="form-input" style="padding-left: 1rem;" placeholder="C.P." required maxlength="5">
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Colonia / Barrio <span class="required">*</span></label>
                        <div id="neighborhoodContainer">
                            <input type="text" id="neighborhood" class="form-input" style="padding-left: 1rem;" placeholder="Escribe tu colonia" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">Municipio</label>
                        <input type="text" id="municipality" class="form-input" style="padding-left: 1rem;" placeholder="Municipio" required>
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Estado</label>
                        <input type="text" id="state" class="form-input" style="padding-left: 1rem;" placeholder="Querétaro" required value="Querétaro">
                    </div>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">Celular <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="whatsapp_number" class="form-input" placeholder="10 dígitos" required>
                            <i class="fa-solid fa-mobile-screen"></i>
                        </div>
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Email</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" class="form-input" placeholder="tu@email.com">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                    </div>
                </div>

                <div id="map"></div>
                <input type="hidden" id="latitude">
                <input type="hidden" id="longitude">

                <div class="nav-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(1)"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(3)">Siguiente <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- STEP 3: Electoral y Especial -->
            <div class="form-step" id="step-3">
                <div class="section-title"><i class="fa-solid fa-building-flag"></i> Perfil CRM / Electoral</div>

                <div class="form-group">
                    <label class="form-label">Clave de Elector (INE)</label>
                    <div class="input-wrapper">
                        <input type="text" id="clave_elector" class="form-input" placeholder="18 caracteres" maxlength="18">
                        <i class="fa-solid fa-check-to-slot"></i>
                    </div>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label class="form-label">Sección</label>
                        <input type="text" id="seccion" class="form-input" style="padding-left: 1rem;" placeholder="0000">
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Vigencia</label>
                        <input type="text" id="vigencia" class="form-input" style="padding-left: 1rem;" placeholder="YYYY">
                    </div>
                    <div class="col form-group">
                        <label class="form-label">Sangre</label>
                        <select id="tipo_sangre" class="form-input" style="padding-left: 0.5rem;">
                            <option value="">N/A</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="A+">A+</option>
                            <option value="B+">B+</option>
                        </select>
                    </div>
                </div>

                <div class="nav-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(2)"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(4)">Siguiente <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- STEP 4: Adicional & Inteligencia -->
            <div class="form-step" id="step-4">
                <div class="section-title"><i class="fa-solid fa-diagram-project"></i> Intereses y Perfil (Árbol Dinámico)</div>

                <div class="form-group">
                    <label class="form-label">Categoría de Interés Principal</label>
                    <select id="main_category" class="form-input" style="padding-left: 1rem;" onchange="updateSubTree()">
                        <option value="">Selecciona...</option>
                        <option value="salud">Salud y Bienestar</option>
                        <option value="educacion">Educación y Cultura</option>
                        <option value="infraestructura">Obras e Infraestructura</option>
                        <option value="social">Apoyo Social</option>
                    </select>
                </div>

                <!-- RAMAS DINÁMICAS (Tree Logic) -->
                <div id="sub_tree_container"></div>

                <div class="card-option">
                    <div class="card-option-header" onclick="document.getElementById('is_leader').click()">
                        <input type="checkbox" id="is_leader">
                        <label><i class="fa-solid fa-star" style="color:var(--secondary);"></i> Deseo ser Líder de Zona</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Número de niños menores a 5 años en casa</label>
                    <div class="input-wrapper">
                        <input type="number" id="children_under_5_count" class="form-input" placeholder="0" min="0" value="0">
                        <i class="fa-solid fa-child"></i>
                    </div>
                </div>

                <!-- Mascotas -->
                <div class="card-option">
                    <div class="card-option-header" onclick="document.getElementById('has_pets').click()">
                        <input type="checkbox" id="has_pets" onchange="togglePets()">
                        <label><i class="fa-solid fa-dog" style="color:#a855f7;"></i> Tengo mascotas</label>
                    </div>
                    <div id="pet-section" class="conditional-fields">
                        <div class="row">
                            <div class="col form-group">
                                <label class="form-label">Nombre</label>
                                <input type="text" id="pet_name" class="form-input" style="padding-left: 1rem;" placeholder="Ej: Zeus">
                            </div>
                            <div class="col form-group">
                                <label class="form-label">Tipo</label>
                                <input type="text" id="pet_type" class="form-input" style="padding-left: 1rem;" placeholder="Perro, Gato..">
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="whatsapp_number">

                <div class="nav-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(2)"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fa-solid fa-paper-plane"></i> Enviar Registro</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const leaderId = urlParams.get('leader');
        document.getElementById('whatsapp_number').value = urlParams.get('whatsapp') || '';

        if (leaderId) {
            console.log('Leader detected:', leaderId);
        }

        function togglePregnancy() {
            const gender = document.getElementById('gender').value;
            document.getElementById('preg-card').className = gender === 'M' ? 'card-option' : 'hidden';
        }

        function togglePets() {
            const check = document.getElementById('has_pets').checked;
            document.getElementById('pet-section').style.display = check ? 'block' : 'none';
        }

        function showNotification(msg, isError = false) {
            const n = document.getElementById('notification');
            n.querySelector('span').innerText = msg;
            n.className = isError ? 'show error' : 'show';
            setTimeout(() => n.className = '', 3000);
        }

        // Map Logic
        const corregidoraCenter = [20.5332, -100.4432];
        const map = L.map('map').setView(corregidoraCenter, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        let marker = L.marker(corregidoraCenter, { draggable: true }).addTo(map);

        function updateCoords(lat, lng) {
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
        }

        marker.on('dragend', (e) => {
            const pos = marker.getLatLng();
            updateCoords(pos.lat, pos.lng);
        });

        map.on('click', (e) => {
            marker.setLatLng(e.latlng);
            updateCoords(e.latlng.lat, e.latlng.lng);
        });

        /* Tree Logic for Branch options */
        const treeData = {
            salud: ['Seguro Popular', 'Jornadas Médicas', 'Medicamentos'],
            educacion: ['Becas', 'Talleres', 'Escuelas de Oficios'],
            infraestructura: ['Alumbrado', 'Bacheo', 'Parques'],
            social: ['Apoyos Despensa', 'Madres Jefas', 'Adultos Mayores']
        };

        function updateSubTree() {
            const cat = document.getElementById('main_category').value;
            const container = document.getElementById('sub_tree_container');
            container.innerHTML = '';
            
            if (treeData[cat]) {
                let html = `<div style="margin-top:1rem; animation:fadeIn 0.3s;">
                    <label class="form-label">Especifique su interés en ${cat}:</label>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;">`;
                
                treeData[cat].forEach(opt => {
                    html += `
                        <label class="card-option" style="margin-top:0; padding:0.5rem 1rem; display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" class="tree-option" value="${opt}"> ${opt}
                        </label>`;
                });
                html += `</div></div>`;
                container.innerHTML = html;
            }
        }

        async function centerOnArea(area, zip) {
            try {
                const query = `${area}, ${zip}, Querétaro, México`;
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`);
                const data = await res.json();
                if (data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    map.setView([lat, lon], 15);
                    marker.setLatLng([lat, lon]);
                    updateCoords(lat, lon);
                }
            } catch (e) { console.error("Geocoding failed", e); }
        }

        /* CP Lookup Logic */
        document.getElementById('postal_code').addEventListener('input', async (e) => {
            const cp = e.target.value;
            if (cp.length === 5) {
                try {
                    const res = await fetch(`/api/public/postal-code/${cp}`, {
                        headers: { 'ngrok-skip-browser-warning': 'true' }
                    });
                    const d = await res.json();
                    if (d.success) {
                        document.getElementById('municipality').value = d.municipio;
                        document.getElementById('state').value = d.estado;

                        const container = document.getElementById('neighborhoodContainer');
                        let selectHtml = `<select id="neighborhood" class="form-input" style="padding-left: 1rem; width:100%;">`;
                        d.colonias.forEach(col => {
                            selectHtml += `<option value="${col}">${col}</option>`;
                        });
                        selectHtml += `<option value="OTRA">OTRA (Escribir manual)</option></select>`;
                        
                        container.innerHTML = selectHtml;

                        document.getElementById('neighborhood').addEventListener('change', (ev) => {
                            if (ev.target.value === 'OTRA') {
                                container.innerHTML = `<input type="text" id="neighborhood" class="form-input" style="padding-left: 1rem;" placeholder="Escribe tu colonia" required>`;
                            } else {
                                centerOnArea(ev.target.value, cp);
                            }
                        });

                        centerOnArea(d.municipio, cp);
                    }
                } catch (err) { console.error("CP Error:", err); }
            }
        });

        /* Stepper Logic */
        function nextStep(step) {
            if (step === 2) {
                if (!document.getElementById('identification_number').checkValidity() || !document.getElementById('name').checkValidity() || !document.getElementById('last_name').checkValidity() || !document.getElementById('age').checkValidity()) {
                    showNotification('Por favor, llena los campos obligatorios.', true);
                    return;
                }
            }
            if (step === 3) {
                 if (!document.getElementById('street').checkValidity() || !document.getElementById('neighborhood').checkValidity() || !document.getElementById('municipality').checkValidity()) {
                    showNotification('Por favor, llena los campos de ubicación.', true);
                    return;
                }
            }

            document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
            document.getElementById(`step-${step}`).classList.add('active');
            
            // Progress Update
            const progress = document.getElementById('progress');
            if(step === 1) progress.style.width = '33%';
            if(step === 2) progress.style.width = '66%';
            if(step === 3) progress.style.width = '100%';
        }

        function prevStep(step) {
            document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
            document.getElementById(`step-${step}`).classList.add('active');
            
            const progress = document.getElementById('progress');
            if(step === 1) progress.style.width = '33%';
            if(step === 2) progress.style.width = '66%';
            if(step === 3) progress.style.width = '100%';
        }

        /* Submit */
        document.getElementById('censoForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Registrando...';
            btn.disabled = true;

            const hasPets = document.getElementById('has_pets') ? document.getElementById('has_pets').checked : false;
            const treeOptions = [...document.querySelectorAll('.tree-option:checked')].map(cb => cb.value);

            const payload = {
                curp: document.getElementById('curp').value,
                identification_number: document.getElementById('identification_number').value,
                clave_elector: document.getElementById('clave_elector').value,
                seccion: document.getElementById('seccion').value,
                vigencia: document.getElementById('vigencia').value,
                tipo_sangre: document.getElementById('tipo_sangre').value,
                nombre: document.getElementById('name').value,
                apellido_paterno: document.getElementById('last_name').value,
                apellido_materno: document.getElementById('maternal_name').value,
                edad: parseInt(document.getElementById('age').value),
                sexo: document.getElementById('gender').value,
                email: document.getElementById('email').value,
                street: document.getElementById('street').value,
                external_number: document.getElementById('external_number').value,
                internal_number: document.getElementById('internal_number').value,
                neighborhood: document.getElementById('neighborhood').value,
                postal_code: document.getElementById('postal_code').value,
                municipality: document.getElementById('municipality').value,
                state: document.getElementById('state').value,
                whatsapp_number: document.getElementById('whatsapp_number').value,
                latitude: document.getElementById('latitude').value,
                longitude: document.getElementById('longitude').value,
                category: document.getElementById('main_category').value,
                tags: treeOptions,
                leader_id: leaderId,
                is_leader: document.getElementById('is_leader').checked
            };

            try {
                const response = await fetch('/api/public/register', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'ngrok-skip-browser-warning': 'true'
                    },
                    body: JSON.stringify(payload)
                });

                const resData = await response.json();

                if (response.ok && resData.success) {
                    showNotification('¡Registro Completo!');
                    document.getElementById('form-container').innerHTML = `
                        <div style="text-align: center; padding: 2rem; animation: slideIn 0.5s ease;">
                            <i class="fa-solid fa-circle-check" style="font-size: 4rem; color: var(--accent); margin-bottom: 1.5rem;"></i>
                            <h2 style="font-weight: 800; margin-bottom: 0.5rem; background:linear-gradient(to right, #10b981, #34d399); -webkit-background-clip:text; -webkit-text-fill-color:transparent;">¡Éxito!</h2>
                            <p style="color: var(--text-muted);">Tus datos se registraron correctamente en el CRM. Ya puedes regresar a WhatsApp.</p>
                        </div>
                    `;
                } else {
                    showNotification(resData.message || 'Error en el registro', true);
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Intentar de nuevo';
                    btn.disabled = false;
                }
            } catch (error) {
                showNotification('Error de conexión', true);
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Intentar de nuevo';
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
