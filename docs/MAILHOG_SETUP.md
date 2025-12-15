# Mailhog Email Testing Setup

## Overview

Mailhog is now configured for testing email functionality in the Palanca Play API. It captures all outgoing emails and provides a web interface to view them.

## Quick Start

### 1. Start Mailhog
```bash
docker-compose up -d
```

### 2. Access Mailhog Web UI
Open your browser and navigate to:
```
http://localhost:8025
```

### 3. Test Emails
```bash
# Test all email types
php artisan email:test all

# Test specific email type
php artisan email:test booking-created
php artisan email:test booking-cancelled
php artisan email:test password-reset
```

## Configuration

### Docker Compose
Mailhog is configured in `docker-compose.yml`:
- **SMTP Server**: Port 1025
- **Web UI**: Port 8025

### Laravel Configuration
Update your `.env` file with:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@palancaplay.com"
MAIL_FROM_NAME="Palanca Play"
```

## Testing Workflow

### 1. Start Mailhog
```bash
docker-compose up -d
```

### 2. Check Container Status
```bash
docker ps
```
You should see `palanca_mailhog` running.

### 3. Run Email Tests
```bash
# Test all emails
php artisan email:test all
```

### 4. View Emails
1. Open http://localhost:8025 in your browser
2. You should see all test emails in the inbox
3. Click on any email to view the HTML content

### 5. Test via API
You can also test by making actual API calls:

**Test Password Reset:**
```bash
curl -X POST http://localhost:8000/api/mobile/v1/password/forgot \
  -H "Content-Type: application/json" \
  -d '{"email":"test@palancaplay.com"}'
```

**Test Booking Creation:**
Create a booking via the mobile API and check Mailhog for the confirmation email.

## Mailhog Features

### Web UI Features
- **Inbox**: View all captured emails
- **Preview**: See HTML and plain text versions
- **Search**: Find specific emails
- **Delete**: Clear emails from inbox
- **Source**: View raw email source

### API Endpoints
Mailhog also provides an API:
- `GET http://localhost:8025/api/v2/messages` - List all messages
- `GET http://localhost:8025/api/v2/messages/{id}` - Get specific message
- `DELETE http://localhost:8025/api/v1/messages` - Delete all messages

## Troubleshooting

### Mailhog Not Starting
```bash
# Check Docker logs
docker logs palanca_mailhog

# Restart container
docker-compose restart mailhog
```

### Emails Not Appearing
1. Check `.env` configuration
2. Verify SMTP settings: `MAIL_HOST=127.0.0.1` and `MAIL_PORT=1025`
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify Mailhog is running: `docker ps`

### Port Already in Use
If port 1025 or 8025 is already in use, modify `docker-compose.yml`:
```yaml
ports:
  - "1026:1025"  # Change external port
  - "8026:8025"  # Change external port
```

Then update `.env`:
```env
MAIL_PORT=1026
```

## Stopping Mailhog

```bash
# Stop container
docker-compose down

# Stop and remove volumes
docker-compose down -v
```

## Production Notes

⚠️ **Important**: Mailhog is for **development and testing only**. 

For production:
1. Use a real SMTP service (SendGrid, Mailgun, AWS SES, etc.)
2. Update `.env` with production SMTP credentials
3. Remove or comment out Mailhog from `docker-compose.yml`

## Email Templates Available

The following email templates are ready to test:

1. **Booking Created** (`booking-created.blade.php`)
   - Purple gradient design
   - Shows court, date, time, price
   - QR code reminder

2. **Booking Updated** (`booking-updated.blade.php`)
   - Pink/red gradient design
   - Yellow warning accent
   - Updated booking details

3. **Booking Cancelled** (`booking-cancelled.blade.php`)
   - Gray gradient design
   - Red "CANCELADA" badge
   - Strikethrough details

4. **Password Reset** (`password-reset-code.blade.php`)
   - Blue gradient design
   - Large 6-digit code display
   - Security warnings

## Next Steps

1. ✅ Mailhog is running
2. ✅ Email configuration updated
3. ✅ Test command created
4. ⏳ Run tests: `php artisan email:test all`
5. ⏳ Check Mailhog UI: http://localhost:8025
6. ⏳ Test via API endpoints
