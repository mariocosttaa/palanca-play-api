<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Recuperação de Senha</title>
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
            background-color: #2d5f3f;
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
            text-align: center;
        }
        .message {
            font-size: 16px;
            color: #495057;
            margin-bottom: 30px;
        }
        .code-box {
            background-color: #f0f7f4;
            border: 2px solid #2d5f3f;
            color: #2d5f3f;
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 12px;
            padding: 30px;
            margin: 30px 0;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
            border-radius: 4px;
        }
        .warning strong {
            color: #856404;
        }
        .warning ul {
            margin: 10px 0;
            padding-left: 20px;
            color: #856404;
        }
        .warning li {
            margin: 8px 0;
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
            <h1>Recuperação de Senha</h1>
            <div class="brand">Palanca Play</div>
        </div>
        <div class="content">
            <div class="message">
                Você solicitou a recuperação de senha.<br>
                Use o código abaixo para redefinir sua senha:
            </div>
            
            <div class="code-box">
                {{ $code }}
            </div>

            <div class="warning">
                <strong>⚠️ Importante:</strong>
                <ul>
                    <li>Este código expira em <strong>15 minutos</strong></li>
                    <li>Não compartilhe este código com ninguém</li>
                    <li>Se você não solicitou esta recuperação, ignore este email</li>
                </ul>
            </div>

            <p style="color: #6c757d; font-size: 14px; margin-top: 30px;">
                Digite este código no aplicativo para criar uma nova senha.
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
