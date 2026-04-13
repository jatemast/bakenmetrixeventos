<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Metrix | Ciudadanía</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --accent: #10b981;
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        header {
            text-align: center;
            margin-bottom: 2rem;
        }

        h1 {
            font-size: 1.8rem;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin: 0;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.5s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card h2 {
            font-size: 1.1rem;
            margin-top: 0;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .input-group {
            margin-bottom: 1.1rem;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--text-dim);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .input-group label .required {
            color: var(--danger);
            margin-left: 2px;
        }

        input, select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(0, 0, 0, 0.2);
            color: white;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        input::placeholder { color: rgba(255,255,255,0.25); }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.75rem;
        }

        @media (max-width: 480px) {
            .row-3 { grid-template-columns: 1fr 1fr; }
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            border-radius: 0.8rem;
            border: none;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-submit:active { transform: scale(0.98); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        #map {
            height: 250px;
            width: 100%;
            border-radius: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 5;
        }

        .leaflet-container {
            background: #0f172a;
        }

        .btn-geo {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--primary);
            background-color: transparent;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 0.75rem;
            transition: background 0.2s;
        }

        .btn-geo:active { background: rgba(99, 102, 241, 0.1); }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

        .hidden { display: none; }

        .pill-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .pill-group label {
            background-color: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 0.45rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-dim);
        }
        .pill-group label:has(input:checked) {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            border-color: var(--primary);
        }
        .pill-group input[type="checkbox"] {
            display: none;
        }

        .universe-module {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 0.8rem;
            margin-bottom: 0.6rem;
            overflow: hidden;
        }
        .universe-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            cursor: pointer;
            background: rgba(0,0,0,0.2);
        }
        .universe-toggle input { display: none; }
        .toggle-text { font-weight: 600; font-size: 0.88rem; }
        .toggle-switch {
            width: 40px; height: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            position: relative;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 16px; height: 16px;
            border-radius: 50%;
            background: white;
            top: 2px; left: 2px;
            transition: all 0.3s;
        }
        .universe-toggle input:checked ~ .toggle-switch {
            background: var(--primary);
        }
        .universe-toggle input:checked ~ .toggle-switch::after {
            left: 22px;
            background: #000;
        }
        .sub-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            background: rgba(0,0,0,0.3);
            padding: 0 1rem;
        }
        .sub-menu.visible {
            max-height: 500px;
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* Success Screen */
        .success-screen {
            text-align: center;
            padding: 3rem 1.5rem;
            animation: fadeIn 0.5s ease;
        }
        .success-screen .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .success-screen h2 {
            display: block;
            font-size: 1.5rem;
            background: linear-gradient(to right, #10b981, #34d399);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }
        .success-screen p {
            color: var(--text-dim);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>REGISTRO INTELIGENTE</h1>
            <p style="color: var(--text-dim);">Construyendo el futuro de la ciudad en 360°</p>
        </header>

        <div id="eventHeader" class="card" style="display: none; border-color: var(--primary); border-width: 2px;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="font-size: 2rem;">🗓️</div>
                <div>
                    <h2 style="margin: 0;" id="eventName">Evento Especial</h2>
                    <p style="color: var(--text-dim); font-size: 0.85rem;" id="eventDate">Pronto estaremos contigo.</p>
                </div>
            </div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.8rem; color: var(--accent);">
                🔥 ¡Gana <span id="eventPoints">5</span> puntos por completar tu asistencia!
            </div>
        </div>

        <form id="superPersonaForm">
            <!-- Sección 1: Identidad Completa -->
            <div class="card">
                <h2>👤 Datos Personales</h2>

                <div class="input-group">
                    <label>WhatsApp / Teléfono <span class="required">*</span></label>
                    <input type="text" id="whatsapp" value="{{ $whatsapp ?? '' }}" {{ isset($whatsapp) ? 'readonly' : 'required' }} placeholder="Ej: 573042697017" style="{{ isset($whatsapp) ? 'opacity: 0.7; cursor: not-allowed;' : '' }}">
                </div>

                <div class="input-group">
                    <label>Cédula / INE / Identificación</label>
                    <input type="text" id="cedula" placeholder="Clave de elector o No. de identificación" autocomplete="off">
                </div>

                <div class="input-group">
                    <label>Nombre(s) <span class="required">*</span></label>
                    <input type="text" id="nombre" placeholder="Ej: Edwin José" required>
                </div>

                <div class="row">
                    <div class="input-group">
                        <label>Apellido Paterno <span class="required">*</span></label>
                        <input type="text" id="apellido_paterno" placeholder="Ej: Abello" required>
                    </div>
                    <div class="input-group">
                        <label>Apellido Materno</label>
                        <input type="text" id="apellido_materno" placeholder="Ej: Acuña">
                    </div>
                </div>

                <div class="row">
                    <div class="input-group">
                        <label>Edad <span class="required">*</span></label>
                        <input type="number" id="edad" placeholder="Años" min="15" max="100" required>
                    </div>
                    <div class="input-group">
                        <label>Género <span class="required">*</span></label>
                        <select id="sexo" required>
                            <option value="" disabled selected>Selecciona...</option>
                            <option value="H">Hombre</option>
                            <option value="M">Mujer</option>
                            <option value="O">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="input-group">
                        <label>Email (Opcional)</label>
                        <input type="email" id="email" placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <div class="row">
                    <div class="input-group">
                        <label>CURP (18 dígitos) <span class="required">*</span></label>
                        <input type="text" id="curp" placeholder="ABC1234567890" maxlength="18" required>
                    </div>
                    <div class="input-group">
                        <label>Tipo de Sangre</label>
                        <select id="tipo_sangre">
                            <option value="">Desconocido</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sección Electorales -->
            <div class="card">
                <h2>🗳️ Datos Electorales</h2>
                <div class="input-group">
                    <label>Clave de Elector</label>
                    <input type="text" id="clave_elector" placeholder="Ej: ABCDEF12345678H900">
                </div>
                <div class="row">
                    <div class="input-group">
                        <label>Sección</label>
                        <input type="number" id="seccion" placeholder="0000">
                    </div>
                    <div class="input-group">
                        <label>Vigencia (Año)</label>
                        <input type="number" id="vigencia" placeholder="2034">
                    </div>
                </div>
            </div>

            <!-- Sección Clasificación -->
            <div class="card">
                <h2>📊 Clasificación y Servicios</h2>
                <div class="row">
                    <div class="input-group">
                        <label>Categoría</label>
                        <input type="text" id="categoria" placeholder="Ej: Preferente, General">
                    </div>
                    <div class="input-group">
                        <label>Tarifa</label>
                        <input type="text" id="tarifa" placeholder="Ej: Social">
                    </div>
                </div>
                <div class="input-group">
                    <label>Servicios de Interés</label>
                    <input type="text" id="servicios" placeholder="Ej: Agua, Luz, Predial">
                </div>
            </div>

            <!-- Sección 2: Ubicación y Dirección Completa -->
            <div class="card">
                <h2>📍 Ubicación y Dirección</h2>

                <button type="button" id="getGeoBtn" class="btn-geo">
                    🎯 Auto-Detectar GPS Actual
                </button>
                <div id="geoStatus" style="font-size: 0.8rem; text-align: center; color: var(--text-dim); margin-bottom: 1rem;">
                    O elige tu ubicación exacta en el mapa
                </div>
                
                <div id="map"></div>

                <input type="hidden" id="latitude">
                <input type="hidden" id="longitude">

                <div class="input-group">
                    <label>Código Postal <span class="required">*</span></label>
                    <input type="text" id="codigo_postal" placeholder="C.P." maxlength="5" required>
                </div>

                <div id="extendedAddress" style="display: none; transition: all 0.5s ease-in-out;">
                    <div class="input-group">
                        <label>Calle</label>
                        <input type="text" id="calle" placeholder="Nombre de la calle">
                    </div>

                    <div class="input-group">
                        <label>Colonia / Barrio <span class="required">*</span></label>
                        <div id="coloniaContainer">
                            <input type="text" id="colonia" placeholder="Escribe tu colonia" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="input-group">
                            <label>Num. Exterior <span class="required">*</span></label>
                            <input type="text" id="numero_exterior" placeholder="Ej: 123" required>
                        </div>
                        <div class="input-group">
                            <label>Num. Interior</label>
                            <input type="text" id="numero_interior" placeholder="Ej: 4B">
                        </div>
                    </div>

                    <div class="row">
                        <div class="input-group">
                            <label>Municipio</label>
                            <input type="text" id="municipio" placeholder="Ej: Soledad" readonly>
                        </div>
                        <div class="input-group">
                            <label>Estado</label>
                            <input type="text" id="estado" placeholder="Ej: Querétaro" value="Querétaro" readonly>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Sección 3: Universos CRM -->
            <div class="card">
                <h2>🌌 Perfil de Intereses</h2>
                <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 1rem;">
                    Activa las categorías que aplican para segmentar al ciudadano.
                </p>

                <!-- Universo Deportes -->
                <div class="universe-module">
                    <label class="universe-toggle">
                        <input type="checkbox" class="universe-check" data-universe="deportes" onchange="toggleSubMenu('sub-deportes', this.checked)">
                        <span class="toggle-text">⚽ Deportes y Actividad Física</span>
                        <div class="toggle-switch"></div>
                    </label>
                    <div id="sub-deportes" class="sub-menu hidden">
                        <div class="pill-group">
                            <label><input type="checkbox" class="auto-tag" data-tag="Fútbol"> Fútbol</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Ciclismo"> Ciclismo</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Correr / Running"> Running</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Gimnasio"> Gimnasio</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Béisbol"> Béisbol</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Natación"> Natación</label>
                        </div>
                    </div>
                </div>

                <!-- Universo Mascotas -->
                <div class="universe-module">
                    <label class="universe-toggle">
                        <input type="checkbox" class="universe-check" data-universe="mascotas" onchange="toggleSubMenu('sub-mascotas', this.checked)">
                        <span class="toggle-text">🐶 Mascotas y Bienestar Animal</span>
                        <div class="toggle-switch"></div>
                    </label>
                    <div id="sub-mascotas" class="sub-menu hidden">
                        <div class="pill-group">
                            <label><input type="checkbox" class="auto-tag" data-tag="Dueño de Perro"> Tengo Perro(s)</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Dueño de Gato"> Tengo Gato(s)</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Rescatista"> Soy Rescatista</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Vacunación Animal"> Interés en Vacunación</label>
                        </div>
                    </div>
                </div>

                <!-- Universo Familia -->
                <div class="universe-module">
                    <label class="universe-toggle">
                        <input type="checkbox" class="universe-check" data-universe="familia" onchange="toggleSubMenu('sub-familia', this.checked)">
                        <span class="toggle-text">👨‍👩‍👧 Familia y Dependientes</span>
                        <div class="toggle-switch"></div>
                    </label>
                    <div id="sub-familia" class="sub-menu hidden">
                        <div class="pill-group">
                            <label><input type="checkbox" class="auto-tag" data-tag="Madre/Padre Cabeza de Familia"> Cabeza de Familia</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Hijos menores de 5"> Hijos (0-5 años)</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Hijos en Edad Escolar"> Hijos Escolarizados</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Adulto Mayor (60+)"> Es Adulto Mayor</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Cuida Adulto Mayor"> Cuida a Adulto Mayor</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Embarazada"> Embarazada</label>
                        </div>
                    </div>
                </div>

                <!-- Universo Salud -->
                <div class="universe-module">
                    <label class="universe-toggle">
                        <input type="checkbox" class="universe-check" data-universe="salud" onchange="toggleSubMenu('sub-salud', this.checked)">
                        <span class="toggle-text">🩺 Salud y Necesidades Especiales</span>
                        <div class="toggle-switch"></div>
                    </label>
                    <div id="sub-salud" class="sub-menu hidden">
                        <div class="pill-group">
                            <label><input type="checkbox" class="auto-tag" data-tag="Discapacidad Motriz"> Discap. Motriz</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Discapacidad Visual"> Discap. Visual</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Enfermedad Crónica"> Diabetes/Hipertensión</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Apoyo Psicológico"> Apoyo Psicológico</label>
                        </div>
                    </div>
                </div>

                <!-- Universo Empleo -->
                <div class="universe-module">
                    <label class="universe-toggle">
                        <input type="checkbox" class="universe-check" data-universe="empleo" onchange="toggleSubMenu('sub-empleo', this.checked)">
                        <span class="toggle-text">💼 Empleo y Economía</span>
                        <div class="toggle-switch"></div>
                    </label>
                    <div id="sub-empleo" class="sub-menu hidden">
                        <div class="pill-group">
                            <label><input type="checkbox" class="auto-tag" data-tag="Busca Empleo"> Desempleado</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Emprendedor"> Emprendedor / MiPyme</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Comercio Informal"> Informal / Ambulante</label>
                        </div>
                    </div>
                </div>

                <!-- Universo Movilidad -->
                <div class="universe-module">
                    <label class="universe-toggle">
                        <input type="checkbox" class="universe-check" data-universe="movilidad" onchange="toggleSubMenu('sub-movilidad', this.checked)">
                        <span class="toggle-text">🚘 Movilidad y Transporte</span>
                        <div class="toggle-switch"></div>
                    </label>
                    <div id="sub-movilidad" class="sub-menu hidden">
                        <div class="pill-group">
                            <label><input type="checkbox" class="auto-tag" data-tag="Transporte Público"> Transporte Público</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Motociclista"> Motociclista</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Vehículo Propio"> Vehículo Propio</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Conductor de App"> Conductor App/Taxi</label>
                        </div>
                    </div>
                </div>

                <!-- Universo Político -->
                <div class="universe-module">
                    <label class="universe-toggle">
                        <input type="checkbox" class="universe-check" data-universe="politico" onchange="toggleSubMenu('sub-politico', this.checked)">
                        <span class="toggle-text">🏛️ Participación Ciudadana</span>
                        <div class="toggle-switch"></div>
                    </label>
                    <div id="sub-politico" class="sub-menu hidden">
                        <div class="pill-group">
                            <label><input type="checkbox" class="auto-tag" data-tag="Militante"> Militante</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Líder de Colonia"> Líder de Colonia</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Voluntario"> Voluntario</label>
                            <label><input type="checkbox" class="auto-tag" data-tag="Promotor del voto"> Promotor del voto</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 4: Tags Manuales -->
            <div class="card">
                <h2>🏷️ Notas Adicionales (Opcional)</h2>
                <div class="input-group">
                    <input type="text" id="extraTags" placeholder="Ej: Deportes, Estudiante, Cultura..." style="margin-bottom: 0.25rem;">
                    <p style="font-size: 0.7rem; color: var(--text-dim); margin: 0;">Separa por comas si agregas más de uno.</p>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">💾 REGISTRAR CIUDADANO</button>
        </form>

        <div id="successScreen" class="success-screen card" style="display: none;">
            <div class="icon">✅</div>
            <h2>¡Registro Exitoso!</h2>
            <p>Los datos del ciudadano han sido guardados correctamente en el CRM.</p>
            <p style="margin-top: 1rem; font-size: 0.85rem;">Ya puedes cerrar esta ventana o regresar a WhatsApp.</p>
            <button class="btn-submit" style="margin-top: 1.5rem;" onclick="window.location.reload()">📋 Registrar otro ciudadano</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/idb@8/build/umd.js"></script>
    <script>
        // IndexedDB para modo offline
        async function initDB() {
            return idb.openDB('metrix-db', 1, {
                upgrade(db) {
                    db.createObjectStore('sync-personas', { keyPath: 'whatsapp' });
                },
            });
        }

        // Toggle Universos
        function toggleSubMenu(id, isChecked) {
            const menu = document.getElementById(id);
            if (isChecked) {
                menu.classList.remove('hidden');
                setTimeout(() => menu.classList.add('visible'), 10);
            } else {
                menu.classList.remove('visible');
                menu.querySelectorAll('input[type="checkbox"]').forEach(i => i.checked = false);
            }
        }

        // --- LÓGICA DE MAPA Y GEOLOCALIZACIÓN ---
        const corregidoraCenter = [20.5332, -100.4432];
        const map = L.map('map').setView(corregidoraCenter, 13);
        const geoStatus = document.getElementById('geoStatus');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        let marker = L.marker(corregidoraCenter, { draggable: true }).addTo(map);

        function updateCoords(lat, lng, shouldReverse = true) {
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            geoStatus.innerText = `✅ Ubicación fija: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
            geoStatus.style.color = '#10b981';
            
            if (shouldReverse) {
                reverseGeocode(lat, lng);
            }
        }

        // Reverse Geocoding: Del Mapa -> A los Campos
        async function reverseGeocode(lat, lng) {
            geoStatus.innerText = "🔍 Identificando dirección...";
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                const data = await res.json();
                if (data.address) {
                    const addr = data.address;
                    if (addr.road) document.getElementById('calle').value = addr.road;
                    if (addr.city || addr.town || addr.village) {
                        document.getElementById('municipio').value = addr.city || addr.town || addr.village;
                    }
                    if (addr.postcode) {
                        const cpField = document.getElementById('codigo_postal');
                        if (cpField.value !== addr.postcode) {
                            cpField.value = addr.postcode;
                            // Disparar búsqueda de CP si cambió
                            lookupCP(addr.postcode, false); 
                        }
                    }
                    
                    const colonia = addr.suburb || addr.neighbourhood || addr.city_district || '';
                    if (colonia) {
                        updateColoniaUI(colonia);
                    }
                }
            } catch (e) { 
                console.error("Reverse Geocode Error", e);
                geoStatus.innerText = "⚠️ Error al identificar dirección";
            }
        }

        function updateColoniaUI(coloniaName) {
            const container = document.getElementById('coloniaContainer');
            const currentSelect = document.getElementById('colonia');
            
            // Si es un select, intentar seleccionar la opción
            if (currentSelect && currentSelect.tagName === 'SELECT') {
                let found = false;
                for (let i = 0; i < currentSelect.options.length; i++) {
                    if (currentSelect.options[i].value.toUpperCase() === coloniaName.toUpperCase()) {
                        currentSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    // Si no está en la lista, agregarla temporalmente o cambiar a text
                    container.innerHTML = `<input type="text" id="colonia" value="${coloniaName}" required>`;
                }
            } else {
                document.getElementById('colonia').value = coloniaName;
            }
        }

        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            updateCoords(pos.lat, pos.lng, true);
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateCoords(e.latlng.lat, e.latlng.lng, true);
        });

        // Forward Geocoding: Del CP -> Al Mapa
        async function centerOnArea(area, zip) {
            try {
                const query = `${area}, ${zip}, Querétaro, México`;
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`);
                const data = await res.json();
                if (data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    map.flyTo([lat, lon], 16, { animate: true, duration: 1.5 });
                    marker.setLatLng([lat, lon]);
                    updateCoords(lat, lon, false);
                }
            } catch (e) { console.error("Geocoding failed", e); }
        }

        document.getElementById('getGeoBtn').onclick = () => {
            if ("geolocation" in navigator) {
                document.getElementById('getGeoBtn').innerText = "⏳ Buscando satélites...";
                navigator.geolocation.getCurrentPosition(pos => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    map.flyTo([lat, lng], 17);
                    marker.setLatLng([lat, lng]);
                    updateCoords(lat, lng, true);
                    document.getElementById('getGeoBtn').innerText = "📍 Actualizar GPS";
                }, err => {
                    alert("No se pudo obtener GPS: " + err.message);
                    document.getElementById('getGeoBtn').innerText = "🎯 Reintentar GPS";
                }, { enableHighAccuracy: true });
            }
        };

        // --- LÓGICA DE BÚSQUEDA DE CP ---
        async function lookupCP(cp, shouldMoveMap = true) {
            if (cp.length !== 5) return;
            
            try {
                const res = await fetch(`/api/public/postal-code/${cp}`, {
                    headers: { 'ngrok-skip-browser-warning': 'true' }
                });
                const response = await res.json();
                if (response.success) {
                    const d = response.data; // Usar el nuevo wrapper 'data'
                    
                    // Mostrar campos extendidos
                    document.getElementById('extendedAddress').style.display = 'block';
                    
                    document.getElementById('municipio').value = d.municipio;
                    document.getElementById('estado').value = d.estado;

                    const container = document.getElementById('coloniaContainer');
                    let selectHtml = `<select id="colonia" style="width: 100%; height: 45px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: #000; color: white; padding: 0 1rem;">`;
                    d.colonias.forEach(col => {
                        selectHtml += `<option value="${col}">${col}</option>`;
                    });
                    selectHtml += `<option value="OTRA">OTRA (Escribir manual)</option></select>`;
                    container.innerHTML = selectHtml;

                    document.getElementById('colonia').addEventListener('change', (ev) => {
                        if (ev.target.value === 'OTRA') {
                            container.innerHTML = `<input type="text" id="colonia" placeholder="Escribe tu colonia" required>`;
                        } else if (shouldMoveMap) {
                            centerOnArea(ev.target.value, cp);
                        }
                    });

                    if (shouldMoveMap) {
                        centerOnArea(d.municipio, cp);
                    }
                }
            } catch (err) { console.error("CP Fetch Error:", err); }
        }

        document.getElementById('codigo_postal').addEventListener('input', (e) => {
            lookupCP(e.target.value, true);
        });

        // --- SUBMIT Y CARGA DE DATOS ---
        document.getElementById('superPersonaForm').onsubmit = async (e) => {
            e.preventDefault();
            const autoTags = [...document.querySelectorAll('.auto-tag:checked')].map(cb => cb.dataset.tag);
            const manualTags = document.getElementById('extraTags').value.split(',').map(t => t.trim()).filter(t => t !== '');
            const finalTags = [...new Set([...autoTags, ...manualTags])];
            const activeUniverses = [...document.querySelectorAll('.universe-check:checked')].map(cb => cb.dataset.universe);

            const urlParams = new URLSearchParams(window.location.search);
            const data = {
                whatsapp: document.getElementById('whatsapp').value,
                curp: document.getElementById('curp').value || null,
                cedula: document.getElementById('cedula').value || null,
                nombre: document.getElementById('nombre').value,
                apellido_paterno: document.getElementById('apellido_paterno').value,
                apellido_materno: document.getElementById('apellido_materno').value || '',
                edad: parseInt(document.getElementById('edad').value) || 0,
                sexo: document.getElementById('sexo').value,
                email: document.getElementById('email').value || null,
                calle: document.getElementById('calle').value || '',
                numero_exterior: document.getElementById('numero_exterior').value || '',
                numero_interior: document.getElementById('numero_interior').value || '',
                colonia: document.getElementById('colonia').value || '',
                codigo_postal: document.getElementById('codigo_postal').value || '',
                municipio: document.getElementById('municipio').value || '',
                estado: document.getElementById('estado').value || 'Querétaro',
                latitude: document.getElementById('latitude').value || null,
                longitude: document.getElementById('longitude').value || null,
                tipo_sangre: document.getElementById('tipo_sangre').value || null,
                clave_elector: document.getElementById('clave_elector').value || null,
                seccion: document.getElementById('seccion').value || null,
                vigencia: document.getElementById('vigencia').value || null,
                categoria: document.getElementById('categoria').value || null,
                tarifa: document.getElementById('tarifa').value || null,
                servicios: document.getElementById('servicios').value || null,
                tags: finalTags,
                universes: activeUniverses,
                leader_id: urlParams.get('leader'),
                tenant_id: urlParams.get('tenant') || '7b544b80-315c-4606-aac9-c9846d2d09de', // Default Metrix Tenant
                event_id: urlParams.get('event'),
                timestamp: new Date().toISOString()
            };

            const btn = document.getElementById('submitBtn');
            btn.innerText = "⏳ Guardando...";
            btn.disabled = true;

            try {
                const response = await fetch('/api/public/store-super-persona', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': 'true' },
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    document.getElementById('superPersonaForm').style.display = 'none';
                    document.getElementById('successScreen').style.display = 'block';
                } else {
                    const errorData = await response.json();
                    alert("⚠️ " + (errorData.message || "Error del servidor. Revisa los datos."));
                    btn.disabled = false;
                    btn.innerText = "💾 REGISTRAR CIUDADANO";
                }
            } catch (err) {
                const db = await initDB();
                await db.put('sync-personas', data);
                alert('📡 MODO OFFLINE: Guardado local.');
                document.getElementById('superPersonaForm').style.display = 'none';
                document.getElementById('successScreen').style.display = 'block';
            }
        };

        window.addEventListener('load', async () => {
            const urlParams = new URLSearchParams(window.location.search);
            const eventId = urlParams.get('event');
            if (!eventId) return;

            try {
                const res = await fetch(`/api/public/events/${eventId}`, {
                    headers: { 'ngrok-skip-browser-warning': 'true' }
                });
                const d = await res.json();
                if (d.success) {
                    const evt = d.event;
                    document.getElementById('eventHeader').style.display = 'block';
                    document.getElementById('eventName').innerText = evt.name;
                    document.getElementById('eventDate').innerText = `${evt.date} - ${evt.time} | 📍 ${evt.location}`;
                    document.getElementById('eventPoints').innerText = evt.points;
                }
            } catch (e) { console.error("Error loading event", e); }
        });
    </script>
</body>
</html>
