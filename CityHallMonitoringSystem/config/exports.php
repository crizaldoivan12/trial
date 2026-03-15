<?php

return [
    // PDF generation is significantly heavier than XLSX.
    // Keep this conservative to avoid timeouts/memory spikes in production.
    'pdf_max_rows' => (int) env('EXPORT_PDF_MAX_ROWS', 3000),
];

