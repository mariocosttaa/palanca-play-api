# PDF Patterns

## 🎯 Objective
Generate consistent, printable PDF documents (Invoices, Reports) served via API.

## 🔑 Key Principles
1.  **Dedicated Service**: Use a `PdfService` or specialized classes (e.g., `InvoicePdf`) to handle generation logic.
2.  **Inline Styles**: PDFs require inline CSS or specific engines (DomPDF/Browsershot); avoid external stylesheets.
3.  **Streaming vs Download**: Explicitly control whether the API returns a stream (view in browser) or download (attachment).
4.  **Data Preparation**: Calculate all totals/summaries in the PHP class *before* passing to the View.

## 📝 Standard Pattern

### 1. PDF Class (The Generator)
Wrapper around the underlying library (e.g., DomPDF, Snappy).

```php
namespace App\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    public static function make(Invoice $invoice)
    {
        $data = [
            'invoice' => $invoice,
            'total' => $invoice->items->sum('total'), // Pre-calculate
            'logo' => public_path('img/logo.png'), // Absolute paths for images
        ];

        return Pdf::loadView('pdf.invoice', $data)
            ->setPaper('a4');
    }
}
```

### 2. Controller Usage
Return the PDF as a response.

```php
public function show(Invoice $invoice)
{
    $pdf = InvoicePdf::make($invoice);

    // Option A: Stream (View in browser)
    return $pdf->stream("invoice-{$invoice->number}.pdf");

    // Option B: Download
    // return $pdf->download("invoice-{$invoice->number}.pdf");
}
```

### 3. Blade Template (`resources/views/pdf/invoice.blade.php`)
Use simple HTML/CSS. Flexbox/Grid support depends on the driver (DomPDF has poor support, Browsershot is full Chrome).

```blade
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        .header { width: 100%; border-bottom: 1px solid #ddd; }
        .table { width: 100%; border-collapse: collapse; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice #{{ $invoice->number }}</h1>
    </div>
    <!-- Content -->
</body>
</html>
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Using `asset()` or relative paths for images | Use `public_path()` or `base_path()` |
| Heavy JS/CSS frameworks (Tailwind/Bootstrap) | Minimal custom CSS or specialized print CSS |
| Calculations in Blade | Calculate in Controller/Service |
| Generating PDF inside the Controller method | specific `Pdf` class to isolate logic |
