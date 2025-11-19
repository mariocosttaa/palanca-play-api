# Email Patterns

## 🎯 Objective
Send transactional emails using consistent, responsive templates and typed Mailable classes.

## 🔑 Key Principles
1.  **Use Mailables**: Always create a Mailable class (`php artisan make:mail`).
2.  **Queued by Default**: Implement `ShouldQueue` interface to prevent blocking the API.
3.  **Blade Components**: Use a base layout (`layouts.email`) and components for buttons/tables.
4.  **Typed Properties**: Use PHP 8.2+ constructor property promotion for type safety.

## 📝 Standard Pattern

### 1. The Mailable Class
```php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $trackingUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Order #' . $this->order->number . ' has shipped!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.shipped',
        );
    }
}
```

### 2. The Controller Usage
```php
// In Controller or Action
Mail::to($user)->send(new OrderShipped($order, $url));

// Or specifically queued (if not implementing ShouldQueue)
Mail::to($user)->queue(new OrderShipped($order, $url));
```

### 3. The Blade Template
Use a standardized layout.
```blade
<x-mail::message>
# Order Shipped

Hi {{ $order->user->name }},

Your order **#{{ $order->number }}** is on its way!

<x-mail::button :url="$trackingUrl">
Track Order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Sending mail synchronously | Implement `ShouldQueue` |
| Logic in Blade template | Prepare data in Mailable constructor |
| Hardcoded subjects in View | Define subject in `envelope()` |
| inline HTML styles everywhere | Use Laravel's Markdown Mail components |
