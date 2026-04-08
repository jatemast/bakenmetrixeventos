<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Metrix | Enterprise Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --bg: #030712;
            --card: #111827;
            --primary: #6366f1;
            --accent: #10b981;
            --text: #f3f4f6;
            --border: rgba(255,255,255,0.08);
        }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); margin: 0; }
        .dashboard { display: grid; grid-template-columns: 300px 1fr; min-height: 100vh; }
        
        /* Sidebar Filters */
        aside { background: var(--card); border-right: 1px solid var(--border); padding: 2rem 1.5rem; }
        .logo { font-weight: 800; font-size: 1.5rem; color: var(--primary); margin-bottom: 2rem; display: block; }
        .filter-group { margin-bottom: 1.5rem; }
        .filter-label { display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 0.5rem; }
        .filter-input { width: 100%; padding: 0.75rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.6rem; color: white; outline: none; }
        .filter-input:focus { border-color: var(--primary); }
        
        /* Main Content */
        main { padding: 2rem; overflow-y: auto; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); padding: 1.5rem; border-radius: 1rem; }
        .stat-val { font-size: 2rem; font-weight: 700; }

        /* Table */
        .table-area { background: var(--card); border-radius: 1rem; border: 1px solid var(--border); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: #9ca3af; }
        td { padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.95rem; }
        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; background: rgba(99,102,241,0.2); color: var(--primary); }
        
        #map-view { height: 500px; border-radius: 1rem; display: none; margin-top: 1rem; z-index: 10; }
        .view-toggle { margin-bottom: 1rem; display: flex; gap: 1rem; }
        .btn-toggle { background: var(--card); border: 1px solid var(--border); color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; cursor: pointer; }
        .btn-toggle.active { background: var(--primary); border-color: var(--primary); }

        .btn-filter { width: 100%; background: var(--primary); border: none; padding: 1rem; border-radius: 0.6rem; color: white; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside>
            <span class="logo">BakenMetrix</span>
            <form action="{{ route('admin.dashboard') }}" method="GET">
                <div class="filter-group">
                    <span class="filter-label">Super Buscador</span>
                    <input type="text" name="search" value="{{ request('search') }}" class="filter-input" placeholder="Nombre, CURP, CDZ...">
                </div>
                <div class="filter-group">
                    <span class="filter-label">Categoría</span>
                    <select name="category" class="filter-input">
                        <option value="">Todas</option>
                        @foreach($allCategories as $cat)
                            <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">Municipio</span>
                    <select name="municipio" class="filter-input">
                        <option value="">Todos</option>
                        @foreach($allMunicipios as $mun)
                            <option value="{{ $mun }}" {{ request('municipio') == $mun ? 'selected' : '' }}>{{ $mun }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Aplicar Filtros</button>
            </form>
        </aside>

        <main>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="filter-label">Total Ciudadanos</div>
                    <div class="stat-val">{{ $total }}</div>
                </div>
                <div class="stat-card">
                    <div class="filter-label">Líderes Activos</div>
                    <div class="stat-val" style="color:var(--accent);">{{ $stats['leaders'] }}</div>
                </div>
            </div>

            <div class="view-toggle">
                <button class="btn-toggle active" id="btn-list" onclick="toggleView('list')"><i class="fa-solid fa-list"></i> Lista</button>
                <button class="btn-toggle" id="btn-map" onclick="toggleView('map')"><i class="fa-solid fa-map-location-dot"></i> Mapa Geometría</button>
            </div>

            <div id="list-view" class="table-area">
                <table>
                    <thead>
                        <tr>
                            <th>CDZ Code</th>
                            <th>Nombre</th>
                            <th>CURP / INE</th>
                            <th>Ubicación</th>
                            <th>Categoría</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personas as $p)
                        <tr>
                            <td><span class="badge">{{ $p->codigo_ciudadano }}</span></td>
                            <td>{{ $p->nombre }} {{ $p->apellido_paterno }}</td>
                            <td><small style="opacity: 0.7;">{{ $p->curp ?: $p->clave_elector }}</small></td>
                            <td>{{ $p->municipio }}, {{ $p->colonia }}</td>
                            <td>{{ ucfirst($p->categoria) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="padding: 1rem;">
                    {{ $personas->links() }}
                </div>
            </div>

            <div id="map-view"></div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        function toggleView(v) {
            document.getElementById('list-view').style.display = v === 'list' ? 'block' : 'none';
            document.getElementById('map-view').style.display = v === 'map' ? 'block' : 'none';
            document.getElementById('btn-list').classList.toggle('active', v === 'list');
            document.getElementById('btn-map').classList.toggle('active', v === 'map');
            
            if (v === 'map') {
                if (!map) initMap();
                else setTimeout(() => map.invalidateSize(), 200);
            }
        }

        async function initMap() {
            map = L.map('map-view').setView([20.5332, -100.4432], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

            const res = await fetch('/api/admin/map-data');
            const data = await res.json();

            data.forEach(p => {
                L.marker([p.lat, p.lng])
                    .bindPopup(`<b>${p.name}</b><br>Code: ${p.code}<br>Cat: ${p.cat}`)
                    .addTo(map);
            });
        }
    </script>
</body>
</html>
