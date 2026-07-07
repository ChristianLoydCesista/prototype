# DomPDF Manual Install Guide

**Option 1: Manual Download**
1. Download: https://github.com/dompdf/dompdf/releases → `v2.0.8.zip`
2. Extract to `c:/xampp/htdocs/prototype/vendor/dompdf/dompdf/`
3. Copy `vendor/autoload.php` stub if needed.

**Option 2: CMD force:**
`composer require dompdf/dompdf --ignore-platform-reqs`

**Code (generate_pdf.php):**
```php
require_once '../vendor/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
$dompdf = new Dompdf();
$dompdf->loadHtml($template);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$file = $dompdf->output();
file_put_contents($pdf_path, $file);
```

**Template fix:** Img src absolute `/prototype/public/...`

Ready? Install DomPDF, confirm vendor/dompdf/src exists.

