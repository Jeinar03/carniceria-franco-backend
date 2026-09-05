<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirma tu correo</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 650px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #dc3545e6 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 300;
            letter-spacing: 1px;
        }
        .company-name {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 8px;
            font-weight: 500;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .message {
            font-size: 16px;
            color: #495057;
            margin-bottom: 30px;
            line-height: 1.7;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white !important;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
        }
        .fallback {
            margin-top: 25px;
            font-size: 13px;
            color: #6c757d;
            word-break: break-all;
        }
        .expiry-note {
            font-style: italic;
            color: #6c757d;
            font-size: 14px;
            text-align: center;
            margin-top: 20px;
        }
        .footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer-logo {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .footer-disclaimer {
            color: #adb5bd;
            font-size: 12px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Confirma tu correo</h1>
            <div class="company-name">Carnicería Franco</div>
        </div>

        <div class="content">
            <div class="greeting">
                Hola {{ $customer->nombre }},
            </div>

            <div class="message">
                Gracias por registrarte en Carnicería Franco. Para activar tu cuenta y confirmar
                que este correo es tuyo, da clic en el siguiente botón:
            </div>

            <div class="btn-container">
                <a href="{{ $url }}" class="btn">Verificar mi correo</a>
            </div>

            <div class="expiry-note">Este enlace vence en 24 horas.</div>

            <div class="fallback">
                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                {{ $url }}
            </div>
        </div>

        <div class="footer">
            <div class="footer-logo">Carnicería Franco</div>
            <div class="footer-disclaimer">
                Si no creaste esta cuenta, puedes ignorar este correo.
            </div>
        </div>
    </div>
</body>
</html>
