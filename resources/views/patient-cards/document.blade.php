<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Identification Card — {{ $patient->patient_id }}</title>
    <style>
        @page { size: 85.60mm 53.98mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: "DejaVu Sans", sans-serif; color: #172554; background: white; }
        .health-card { position: absolute; left: 0; top: 0; width: 85.60mm; height: 53.98mm; overflow: hidden; background: white; }
        .card-header { height: 10mm; padding: 1.2mm 3mm; background: #1e40af; color: white; }
        .center-name { font-size: 11pt; line-height: 1.3; font-weight: bold; }
        .card-subtitle { font-size: 6pt; line-height: 1.4; letter-spacing: 0.8pt; }
        .card-information { position: absolute; left: 3mm; top: 12mm; width: 54mm; }
        .field { margin-bottom: 1.3mm; }
        .label { font-size: 5.5pt; color: #475569; line-height: 1.2; }
        .value { font-size: 7pt; line-height: 1.25; overflow-wrap: anywhere; word-wrap: break-word; }
        .patient-name { font-weight: bold; font-size: {{ mb_strlen($fullName) > 55 ? '6' : '8' }}pt; }
        .demographics { width: 100%; border-collapse: collapse; margin-bottom: 1.3mm; }
        .demographics td { padding: 0; width: 50%; vertical-align: top; }
        .address { font-size: {{ mb_strlen($patient->address ?? '') > 140 ? '5' : '6' }}pt; line-height: 1.2; }
        .photo-placeholder { position: absolute; right: 3mm; top: 12mm; width: 19mm; height: 19mm; border: 0.3mm solid #94a3b8; background: #f8fafc; }
        .qr-code { position: absolute; right: 3mm; top: 32mm; width: 19mm; height: 19mm; }
        .card-footer { position: absolute; left: 3mm; bottom: 1.3mm; font-size: 4.5pt; color: #64748b; }
        @media print {
            html, body { width: 85.60mm; height: 53.98mm; }
            * { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <article class="health-card" aria-label="Patient identification card">
        <header class="card-header">
            <div class="center-name">Alabang Health Center</div>
            <div class="card-subtitle">PATIENT IDENTIFICATION CARD</div>
        </header>
        <div class="card-information">
            <div class="field">
                <div class="label">PATIENT ID</div>
                <div class="value">{{ $patient->patient_id }}</div>
            </div>
            <div class="field">
                <div class="label">FULL NAME</div>
                <div class="value patient-name">{{ $fullName ?: 'Not available' }}</div>
            </div>
            <table class="demographics">
                <tr>
                    <td><div class="label">DATE OF BIRTH</div><div class="value">{{ $birthdate }}</div></td>
                    <td><div class="label">SEX</div><div class="value">{{ $patient->sex ?: 'Not available' }}</div></td>
                </tr>
            </table>
            <div class="field">
                <div class="label">ADDRESS</div>
                <div class="value address">{{ $patient->address ?: 'Not available' }}</div>
            </div>
        </div>
        <div class="photo-placeholder" role="img" aria-label="Patient photo placeholder"></div>
        <img class="qr-code" src="{{ $qrCode }}" alt="QR code for patient {{ $patient->patient_id }}">
        <footer class="card-footer">Present this card when visiting the health center.</footer>
    </article>
</body>
</html>