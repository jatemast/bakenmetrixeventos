<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Puntos | Metrix Loyalty</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #070912;
            --surface: #121421;
            --primary: #6366f1;
            --secondary: #a855f7;
            --accent: #10b981;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at top left, rgba(99, 102, 241, 0.15), transparent 400px),
                radial-gradient(circle at bottom right, rgba(168, 85, 247, 0.15), transparent 400px);
            color: var(--text-main);
            min-height: 100vh;
            padding: 1rem;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
        }

        /* Profile Header */
        .profile-header {
            text-align: center;
            margin-top: 2rem;
            margin-bottom: 2.5rem;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .avatar-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem;
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.4);
        }

        .badge-vip {
            position: absolute;
            bottom: -5px;
            right: -5px;
            background: #ffd700;
            color: #000;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .profile-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .profile-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Score Card */
        .score-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .score-card::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .score-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }

        .puntos-vivo {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #fff 30%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        .puntos-sigla {
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 600;
            margin-left: 0.25rem;
        }

        /* Stats Row */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.25rem;
            text-align: center;
        }

        .stat-item i {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .stat-value {
            display: block;
            font-weight: 800;
            font-size: 1.2rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* History Section */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding: 0 0.5rem;
        }

        .section-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .history-item {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s;
        }

        .history-item:hover {
            transform: scale(1.02);
            background: rgba(255, 255, 255, 0.05);
        }

        .icon-box {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(99, 102, 241, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .history-info {
            flex: 1;
        }

        .history-info .title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .history-info .date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .history-points {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--accent);
        }

        .history-points.negative {
            color: #ef4444;
        }

        /* Footer */
        .footer-action {
            margin-top: 3rem;
            text-align: center;
            padding-bottom: 2rem;
        }

        .btn-main {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 18px;
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s;
        }

        .btn-main:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.5);
        }

    </style>
</head>
<body>

    <div class="container">
        
        <header class="profile-header">
            <div class="avatar-container">
                <div class="avatar">
                    {{ substr($persona->nombre, 0, 1) }}
                </div>
                <div class="badge-vip">{{ $persona->universe_type ?? 'U1' }}</div>
            </div>
            <h1>{{ $persona->nombre }} {{ $persona->apellido_paterno }}</h1>
            <p><i class="fa-brands fa-whatsapp"></i> {{ $persona->numero_celular }}</p>
        </header>

        <section class="score-card">
            <div class="score-label">Balance Actual</div>
            <div class="puntos-vivo">{{ $persona->loyalty_balance ?? 0 }}<span class="puntos-sigla">PTS</span></div>
            <p style="color: var(--accent); font-weight: 600; font-size: 0.85rem;">
                <i class="fa-solid fa-bolt"></i> Nivel: {{ $persona->loyalty_balance >= 50 ? 'Premium Citizen' : 'Active Citizen' }}
            </p>
        </section>

        @if($persona->is_leader)
        <section style="background: rgba(99, 102, 241, 0.1); border: 1px dashed var(--primary); border-radius: 24px; padding: 1.5rem; margin-bottom: 2rem; text-align: center;">
            <h3 style="font-size: 1rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-share-nodes"></i> Tu Enlace de Invitación</h3>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Gana 3 puntos por cada referido que complete su vacunación.</p>
            <div style="background: rgba(0,0,0,0.3); padding: 0.75rem; border-radius: 12px; font-family: monospace; font-size: 0.8rem; margin-bottom: 1rem; word-break: break-all;" id="referral-link">
                {{ url('/censo-registro') }}?leader={{ $persona->id }}
            </div>
            <button onclick="copyReferral()" class="btn-main" style="padding: 0.6rem 1.25rem; font-size: 0.85rem; border-radius: 12px;">
                <i class="fa-solid fa-copy"></i> Copiar Enlace
            </button>
        </section>

        <script>
            function copyReferral() {
                const link = document.getElementById('referral-link').innerText;
                navigator.clipboard.writeText(link.trim());
                alert('¡Enlace copiado! Compártelo con tus invitados.');
            }
        </script>
        @endif

        <div class="stats-grid">
            <div class="stat-item">
                <i class="fa-solid fa-calendar-check"></i>
                <span class="stat-value">{{ $history->where('type', 'attendance')->count() }}</span>
                <span class="stat-label">Eventos</span>
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-people-group"></i>
                <span class="stat-value">{{ $history->where('type', 'leader_bonus')->count() }}</span>
                <span class="stat-label">Referidos</span>
            </div>
        </div>

        <div class="section-header">
            <h2>Historial de Movimientos</h2>
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--text-muted);"></i>
        </div>

        <div class="history-list">
            @forelse($history as $item)
                <div class="history-item">
                    <div class="icon-box">
                        <i class="fa-solid {{ $item->type == 'attendance' ? 'fa-medal' : ($item->type == 'redemption' ? 'fa-gift' : 'fa-user-plus') }}"></i>
                    </div>
                    <div class="history-info">
                        <div class="title">{{ $item->description }}</div>
                        <div class="date">{{ $item->created_at->format('d M, Y - H:i') }}</div>
                    </div>
                    <div class="history-points {{ $item->points < 0 ? 'negative' : '' }}">
                        {{ $item->points > 0 ? '+' : '' }}{{ $item->points }}
                    </div>
                </div>
            @empty
                <div style="text-align: center; color: var(--text-muted); padding: 2rem;">
                    No hay movimientos registrados aún.
                </div>
            @endforelse
        </div>

        <div class="footer-action">
            <a href="https://wa.me/{{ env('WHATSAPP_BOT_NUMBER') }}" class="btn-main">
                <i class="fa-brands fa-whatsapp"></i> Volver al Asistente
            </a>
        </div>

    </div>

</body>
</html>
