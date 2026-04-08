<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Metrix | Credencial Digital</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --primary: #818cf8;
            --text: #f8fafc;
        }
        body { margin: 0; font-family: 'Outfit', sans-serif; background: var(--bg); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 1rem; color: var(--text); }
        
        .carnet {
            width: 100%;
            max-width: 350px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 2rem;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .header { margin-bottom: 2rem; }
        .logo { font-weight: 800; font-size: 1.5rem; color: var(--primary); letter-spacing: -1px; }
        .type { text-transform: uppercase; font-size: 0.7rem; color: #94a3b8; letter-spacing: 2px; }

        .qr-container {
            background: white;
            padding: 1rem;
            border-radius: 1.5rem;
            display: inline-block;
            margin-bottom: 2rem;
            box-shadow: 0 0 20px rgba(129, 140, 248, 0.3);
        }

        .name { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
        .code { font-family: monospace; font-size: 1.1rem; color: var(--primary); letter-spacing: 2px; margin-bottom: 1.5rem; background: rgba(0,0,0,0.3); padding: 0.5rem; border-radius: 0.5rem; }

        .details { text-align: left; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 1rem; font-size: 0.9rem; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #94a3b8; }
        .detail-val { color: var(--text); font-weight: 600; }

        .footer-note { font-size: 0.75rem; color: #64748b; margin-top: 2rem; }
    </style>
</head>
<body>
    <div class="carnet">
        <div class="header">
            <div class="logo">BAKENMETRIX</div>
            <div class="type">Credencial Oficial de Ciudadano</div>
        </div>

        <div class="qr-container">
            <div id="qrcode"></div>
        </div>

        <div class="name">{{ $persona->nombre }}<br>{{ $persona->apellido_paterno }}</div>
        <div class="code">{{ $persona->codigo_ciudadano }}</div>

        <div class="details">
            <div class="detail-row">
                <span>Estado</span>
                <span class="detail-val">ACTIVO</span>
            </div>
            <div class="detail-row">
                <span>INE</span>
                <span class="detail-val">{{ $persona->clave_elector ?: 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span>Categoría</span>
                <span class="detail-val">{{ ucfirst($persona->categoria ?: 'General') }}</span>
            </div>
        </div>

        <p class="footer-note">Presenta esta credencial en la mesa de control de cualquier evento Metrix para validar tu asistencia y acumular puntos.</p>
    </div>

    <script type="text/javascript">
        new QRCode(document.getElementById("qrcode"), {
            text: "{{ $persona->codigo_ciudadano }}",
            width: 180,
            height: 180,
            colorDark : "#0f172a",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>
