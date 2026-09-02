<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $config['subject'] }}</title>
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
            background: linear-gradient(135deg, {{ $config['color'] }} 0%, {{ $config['color'] }}e6 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 20px;
            background: linear-gradient(to right, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
        }
        .company-name {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 8px;
            font-weight: 500;
        }
        .status-badge {
            display: inline-block;
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 500;
            font-size: 14px;
            margin-top: 15px;
            border: 2px solid rgba(255,255,255,0.3);
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
        .order-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .order-card h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            border-bottom: 2px solid {{ $config['color'] }};
            padding-bottom: 10px;
        }
        .order-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 14px;
        }
        .info-value {
            font-weight: 500;
            color: #2c3e50;
            text-align: right;
        }
        .products-section {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        .products-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #2c3e50;
        }
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f8f9fa;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .product-details {
            flex-grow: 1;
        }
        .product-name {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .product-specs {
            color: #6c757d;
            font-size: 14px;
        }
        .product-total {
            font-weight: 600;
            color: #28a745;
            font-size: 16px;
        }
        .total-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        .total-amount {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }
        .total-label {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .next-step-card {
            background: linear-gradient(135deg, rgba({{ substr($config['color'], 1) }}, 0.1) 0%, rgba({{ substr($config['color'], 1) }}, 0.05) 100%);
            border-left: 4px solid {{ $config['color'] }};
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
        }
        .next-step-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .next-step-content {
            color: #495057;
            margin-bottom: 8px;
        }
        .time-estimate {
            font-style: italic;
            color: #6c757d;
            font-size: 14px;
        }
        .address-card {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .address-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }
        .address-info {
            color: #495057;
            line-height: 1.6;
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
        .footer-text {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .footer-disclaimer {
            color: #adb5bd;
            font-size: 12px;
            font-style: italic;
        }
        @media (max-width: 600px) {
            .container {
                margin: 15px;
            }
            .header, .content {
                padding: 25px 20px;
            }
            .order-info-grid {
                grid-template-columns: 1fr;
            }
            .product-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .product-total {
                margin-top: 8px;
                align-self: flex-end;
            }
            .total-amount {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $config['title'] }}</h1>
            <div class="company-name">Carnicería Franco</div>
            <div class="status-badge">{{ $config['status_display'] }}</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Estimado/a {{ $customer->nombre }} {{ $customer->apellido }}
            </div>

            <div class="message">
                {{ $config['message'] }}
            </div>

            <!-- Order Information -->
            <div class="order-card">
                <h3>Información del Pedido</h3>

                <div class="order-info-grid">
                    <div class="info-item">
                        <span class="info-label">Número de Pedido:</span>
                        <span class="info-value">{{ $order['folio'] }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Compra:</span>
                        <span class="info-value">{{ $order['fecha'] }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total de Items:</span>
                        <span class="info-value">{{ $order['cantidad_items'] }} productos</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado Actual:</span>
                        <span class="info-value">{{ $config['status_display'] }}</span>
                    </div>
                </div>

                <!-- Product List -->
                <div class="products-section">
                    <div class="products-header">
                        Detalle de Productos
                    </div>
                    @foreach($order['productos'] as $producto)
                        <div class="product-item">
                            <div class="product-details">
                                <div class="product-name">{{ $producto['nombre'] }}</div>
                                <div class="product-specs">{{ $producto['cantidad'] }} {{ $producto['unidad'] }} × ${{ $producto['precio'] }}</div>
                            </div>
                            <div class="product-total">${{ $producto['subtotal'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Total -->
            <div class="total-section">
                <div class="total-amount">${{ $order['total'] }}</div>
                <div class="total-label">Total de la Compra</div>
            </div>

            <!-- Next Step -->
            <div class="next-step-card">
                <div class="next-step-title">Próximo Paso:</div>
                <div class="next-step-content">{{ $config['next_step'] }}</div>
                <div class="time-estimate">{{ $config['estimated_time'] }}</div>
            </div>

            <!-- Customer Address -->
            @if($customer->direccion)
            <div class="address-card">
                <h3>Dirección de Entrega</h3>
                <div class="address-info">
                    <strong>Dirección:</strong> {{ $customer->direccion }}<br>
                    @if($customer->telefono)
                    <strong>Teléfono de contacto:</strong> {{ $customer->telefono }}
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">{{ config('app.name') }}</div>
            <div class="footer-text">
                Gracias por confiar en nosotros para la compra de productos cárnicos y alimentos frescos de la mejor calidad.
            </div>
            <div class="footer-disclaimer">
                Este es un correo electrónico automático. Por favor, no responder a este mensaje.
            </div>
        </div>
    </div>
</body>
</html>
