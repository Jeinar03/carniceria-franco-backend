<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $success ? 'Correo verificado' : 'Enlace inválido' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            max-width: 480px;
            width: 90%;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            padding: 40px 30px;
            text-align: center;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        h1 {
            font-size: 22px;
            color: #2c3e50;
            margin: 0 0 15px 0;
        }
        p {
            color: #495057;
            font-size: 15px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        @if($success)
            <div class="icon">✅</div>
            <h1>Correo verificado</h1>
            <p>Tu cuenta en Carnicería Franco ya está confirmada. Puedes regresar a la tienda.</p>
        @else
            <div class="icon">⚠️</div>
            <h1>Enlace inválido o vencido</h1>
            <p>Este enlace de verificación ya no es válido. Inicia sesión en la tienda y solicita que te reenvíen el correo de confirmación.</p>
        @endif
        <a href="{{ $tiendaUrl }}" class="btn">Ir a la tienda</a>
    </div>
</body>
</html>
