<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .email-header {
            background: linear-gradient(135deg, {{ $urgencyColor }} 0%, {{ $urgencyColor }}dd 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }

        .urgency-badge {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.3);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .email-header h1 {
            margin: 10px 0;
            font-size: 24px;
            font-weight: 600;
        }

        .email-body {
            padding: 30px 25px;
            color: #333333;
        }

        .alert-box {
            background-color: #fff3cd;
            border-left: 4px solid {{ $urgencyColor }};
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .alert-box.critical {
            background-color: #f8d7da;
        }

        .alert-box.info {
            background-color: #d1ecf1;
        }

        .batch-details {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: #212529;
            text-align: right;
        }

        .action-section {
            background-color: #e7f3ff;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .action-message {
            font-size: 15px;
            color: #004085;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: {{ $urgencyColor }};
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin-top: 10px;
            transition: opacity 0.3s;
        }

        .button:hover {
            opacity: 0.9;
        }

        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
        }

        .footer-note {
            margin-top: 10px;
            font-style: italic;
        }

        .highlight {
            background-color: #fff3cd;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
        }

        .expired-stamp {
            color: #dc3545;
            font-weight: bold;
            font-size: 18px;
        }

        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 0;
            }
            
            .email-body {
                padding: 20px 15px;
            }

            .detail-row {
                flex-direction: column;
            }

            .detail-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="urgency-badge">{{ $urgencyLevel }}</div>
            <h1>
                @if($notificationType === 'expired')
                    🚫 Lote Vencido
                @elseif($notificationType === 'out_of_stock')
                    📦 Lote Sin Stock
                @else
                    ⏰ Lote Próximo a Vencer
                @endif
            </h1>
            <p style="margin: 5px 0 0 0; font-size: 14px;">
                @if($businessName)
                    {{ $businessName }}
                @else
                    Sistema de Gestión POS
                @endif
            </p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <!-- Alert Box -->
            <div class="alert-box {{ $daysUntilExpiry <= 15 ? 'critical' : ($daysUntilExpiry > 60 ? 'info' : '') }}">
                @if($notificationType === 'expired')
                    <strong class="expired-stamp">⚠️ ESTE LOTE HA VENCIDO</strong>
                @elseif($notificationType === 'out_of_stock')
                    <strong>Este lote se ha agotado completamente</strong>
                @else
                    <strong>Este lote vencerá en <span class="highlight">{{ $daysUntilExpiry }} días</span></strong>
                @endif
            </div>

            <!-- Batch Details -->
            <div class="batch-details">
                <h2 style="margin-top: 0; color: #212529; font-size: 18px;">📋 Detalles del Lote</h2>
                
                <div class="detail-row">
                    <span class="detail-label">Número de Lote:</span>
                    <span class="detail-value">{{ $batch->batch_number }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Producto:</span>
                    <span class="detail-value">{{ $batch->product->productName ?? 'N/A' }}</span>
                </div>

                @if($batch->product && $batch->product->productCode)
                <div class="detail-row">
                    <span class="detail-label">Código del Producto:</span>
                    <span class="detail-value">{{ $batch->product->productCode }}</span>
                </div>
                @endif

                <div class="detail-row">
                    <span class="detail-label">Cantidad Restante:</span>
                    <span class="detail-value">
                        <strong style="color: {{ $batch->remaining_quantity <= 0 ? '#dc3545' : ($batch->remaining_quantity < 10 ? '#ffc107' : '#28a745') }}">
                            {{ $batch->remaining_quantity }} {{ $batch->product->productUnit ?? 'unidades' }}
                        </strong>
                    </span>
                </div>

                @if($batch->expiry_date)
                <div class="detail-row">
                    <span class="detail-label">Fecha de Vencimiento:</span>
                    <span class="detail-value">
                        <strong style="color: {{ $notificationType === 'expired' ? '#dc3545' : ($daysUntilExpiry <= 15 ? '#ff4500' : '#333') }}">
                            {{ \Carbon\Carbon::parse($batch->expiry_date)->format('d/m/Y') }}
                        </strong>
                    </span>
                </div>
                @endif

                @if($batch->manufacture_date)
                <div class="detail-row">
                    <span class="detail-label">Fecha de Fabricación:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($batch->manufacture_date)->format('d/m/Y') }}</span>
                </div>
                @endif

                <div class="detail-row">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value">
                        @if($notificationType === 'expired')
                            <span style="color: #dc3545; font-weight: bold;">❌ Vencido</span>
                        @elseif($notificationType === 'out_of_stock')
                            <span style="color: #6c757d; font-weight: bold;">📦 Agotado</span>
                        @else
                            <span style="color: {{ $urgencyColor }}; font-weight: bold;">⚠️ Activo - Próximo a Vencer</span>
                        @endif
                    </span>
                </div>
            </div>

            <!-- Action Section -->
            <div class="action-section">
                <div class="action-message">
                    {{ $actionMessage }}
                </div>
                <a href="{{ env('APP_URL', '#') }}/admin/batches" class="button">
                    Ver Lote en el Sistema
                </a>
            </div>

            <!-- Additional Info -->
            <p style="font-size: 14px; color: #6c757d; margin-top: 25px;">
                <strong>Nota importante:</strong> Esta notificación se genera automáticamente para ayudarle a gestionar su inventario de manera eficiente y evitar pérdidas por vencimiento.
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p style="margin: 0;">
                <strong>Sistema de Gestión POS</strong><br>
                Notificación Automática de Lotes
            </p>
            <p class="footer-note">
                Este es un email automático. Por favor, no responda a este mensaje.
            </p>
            <p style="margin-top: 15px; font-size: 11px;">
                © {{ date('Y') }} Todos los derechos reservados
            </p>
        </div>
    </div>
</body>
</html>
