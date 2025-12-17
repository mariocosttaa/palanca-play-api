<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Cancelled</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
            background-color: #ffffff;
            color: #24292f;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }
        .container {
            max-width: 544px;
            margin: 0 auto;
            padding: 24px;
            text-align: center;
        }
        .logo {
            margin-bottom: 24px;
            text-align: center;
        }
        .logo-text {
            font-size: 24px;
            font-weight: 600;
            color: #24292f;
            text-decoration: none;
            letter-spacing: -0.5px;
        }
        .content {
            background-color: #ffffff;
            text-align: left;
        }
        .title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #24292f;
            text-align: center;
        }
        .text {
            font-size: 14px;
            color: #24292f;
            margin-bottom: 16px;
            text-align: center;
        }
        .details-box {
            background-color: #f6f8fa;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 16px;
            margin: 24px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #d0d7de;
            font-size: 14px;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #57606a;
        }
        .detail-value {
            color: #cf222e;
            text-align: right;
            text-decoration: line-through;
        }
        .badge-container {
            text-align: center;
            margin-bottom: 24px;
        }
        .cancelled-badge {
            background-color: #ffebe9;
            color: #cf222e;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid #ff818266;
        }
        .footer {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #d0d7de;
            font-size: 12px;
            color: #57606a;
            text-align: center;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-text">PalancaPlay</div>
        </div>
        
        <div class="content">
            <div class="title">Booking Cancelled</div>
            
            <div class="badge-container">
                <span class="cancelled-badge">CANCELLED</span>
            </div>

            <div class="text">
                Your booking has been cancelled.
            </div>

            <div class="details-box">
                <div class="detail-row">
                    <span class="detail-label">Court</span>
                    <span class="detail-value">{{ $booking->court->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($booking->start_date)->format('d/m/Y') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}</span>
                </div>
            </div>
            
            <div class="text" style="margin-top: 32px;">
                If you did not request this cancellation, please contact support immediately.
            </div>
        </div>

        <div class="footer">
            <p>You're receiving this email because a booking was cancelled on PalancaPlay.</p>
            <p>If this wasn't you, please contact support immediately.</p>
        </div>
    </div>
</body>
</html>
