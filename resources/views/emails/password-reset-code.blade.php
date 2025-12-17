<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code</title>
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
            text-align: center;
        }
        .title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #24292f;
        }
        .code-box {
            font-size: 32px;
            font-weight: 600;
            letter-spacing: 4px;
            margin: 32px 0;
            padding: 16px;
            background-color: #f6f8fa;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
            color: #24292f;
            display: inline-block;
            min-width: 200px;
        }
        .text {
            font-size: 14px;
            color: #24292f;
            margin-bottom: 16px;
        }
        .warning {
            font-size: 14px;
            color: #24292f;
            margin-top: 24px;
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
            <div class="title">Reset your password</div>
            
            <div class="text">
                Here is your password reset code:
            </div>

            <div class="code-box">
                {{ $code }}
            </div>

            <div class="text">
                This code is valid for 15 minutes and can only be used once.
            </div>

            <div class="warning">
                <strong>Please don't share this code with anyone:</strong> we'll never ask for it on the phone or via email.
            </div>
            
            <div class="text" style="margin-top: 32px;">
                Thanks,<br>
                The PalancaPlay Team
            </div>
        </div>

        <div class="footer">
            <p>You're receiving this email because a password reset was requested for your PalancaPlay account.</p>
            <p>If this wasn't you, please ignore this email.</p>
        </div>
    </div>
</body>
</html>
