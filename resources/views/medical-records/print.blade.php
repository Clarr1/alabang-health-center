<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Medical Record - {{ $record->consultation_date?->format('d M Y') ?: 'Undated visit' }} - {{ $patient->first_name }} {{ $patient->last_name }}</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; color: #293952; background: #f5f7fb; }
        .toolbar { padding: 16px 24px; background: white; border-bottom: 1px solid #dfe5ed; }
        h1 { font-size: 18px; margin: 0 0 12px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        button, a { font: inherit; font-size: 13px; border: 1px solid #ccd5df; border-radius: 8px; padding: 9px 14px; background: white; color: #293952; text-decoration: none; cursor: pointer; }
        button { background: #bde1f0; } button:disabled { cursor: wait; opacity: 0.5; }
        p { font-size: 13px; } iframe { display: block; width: 100%; height: calc(100vh - 160px); border: 0; }
        @media print { .toolbar { display: none; } iframe { height: 100vh; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>Medical record: {{ $patient->first_name }} {{ $patient->middle_name }} {{ $patient->last_name }} | {{ $record->consultation_date?->format('d M Y') ?: 'Consultation date not recorded' }}</h1>
        <div class="actions">
            <button id="print-button" type="button" disabled>Print</button>
            <a href="{{ route('medical-records.download', $parameters) }}">Download PDF</a>
            <a href="{{ route('medical-records.pdf', $parameters) }}" target="_blank" rel="noopener noreferrer">Open PDF</a>
        </div>
        <p id="print-status" role="status">Preparing the PDF. The print dialog opens when it is ready.</p>
    </div>
    <iframe id="medical-record-pdf" title="Patient medical records PDF"></iframe>
    <script>
        const frame = document.getElementById('medical-record-pdf');
        const button = document.getElementById('print-button');
        const status = document.getElementById('print-status');
        let pdfUrl;
        function printPdf() {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                status.textContent = 'If the dialog did not open, use Print again or Open PDF and the PDF viewer\'s print control.';
            } catch (error) {
                status.textContent = 'Use Open PDF and the PDF viewer\'s print control to print this document.';
            }
        }
        button.addEventListener('click', printPdf);
        async function preparePdf() {
            try {
                const response = await fetch(@json(route('medical-records.pdf', $parameters)), { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) {
                    throw new Error('PDF generation failed');
                }
                const document = await response.blob();
                pdfUrl = URL.createObjectURL(document);
                frame.onload = () => { button.disabled = false; printPdf(); };
                frame.src = pdfUrl;
            } catch (error) {
                status.textContent = 'Unable to prepare the PDF. Your session or access may have changed. Return to the medical record and try again.';
            }
        }
        window.addEventListener('pagehide', () => { if (pdfUrl) { URL.revokeObjectURL(pdfUrl); } });
        preparePdf();
    </script>
</body>
</html>
