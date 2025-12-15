<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva Cancelada</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #6c757d;
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .brand {
            font-size: 16px;
            margin-top: 8px;
            opacity: 0.9;
        }
        .content {
            padding: 40px 30px;
        }
        .cancel-message {
            text-align: center;
            color: #6c757d;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 30px;
        }
        .cancelled-badge {
            background-color: #dc3545;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
            font-size: 14px;
            margin: 20px 0;
        }
        .booking-details {
            background-color: #f8f9fa;
            border-left: 4px solid #6c757d;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .detail-row {
            margin: 12px 0;
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
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
            color: #6c757d;
            text-align: right;
            text-decoration: line-through;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reserva Cancelada</h1>
            <div class="brand">Palanca Play</div>
        </div>
        <div class="content">
            <div class="cancel-message">
                ✕ Sua reserva foi cancelada
            </div>
            
            <div style="text-align: center;">
                <span class="cancelled-badge">CANCELADA</span>
            </div>

            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Campo</span>
                    <span class="detail-value">{{ $booking->court->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Data</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($booking->start_date)->format('d/m/Y') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Horário</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}</span>
                </div>
            </div>

            <p style="color: #6c757d; font-size: 14px; text-align: center; margin-top: 30px;">
                Se você não solicitou este cancelamento, entre em contato conosco imediatamente.
            </p>
        </div>
        <div class="footer">
            <p><strong>Palanca Play</strong></p>
            <p>Sistema de Reservas</p>
            <p style="margin-top: 15px;">Este é um email automático, por favor não responda.</p>
        </div>
    </div>
</body>
</html>
