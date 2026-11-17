<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Identification Card &mdash; {{ $familyNumber ?? 'Family number not assigned' }}</title>
    <style>
        @page { size: 85.60mm 53.98mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: "DejaVu Sans", sans-serif; color: #293952; background: white; }
        .health-card { position: absolute; left: 0; top: 0; width: 85.60mm; height: 53.98mm; overflow: hidden; background: white; border: 0.3mm solid #a8dcf0; border-radius: 2mm; }
        .card-header { position: absolute; top: 3mm; left: 3mm; right: 3mm; height: 10mm; border-bottom: 0.2mm solid #e8eaf0; }
        .brand-logo { position: absolute; top: 0; left: 0; width: 6.5mm; height: auto; }
        .brand-copy { position: absolute; top: 0.4mm; left: 8mm; }
        .center-name { font-size: 6.5pt; line-height: 1.4; font-weight: bold; }
        .card-subtitle { font-size: 4.5pt; line-height: 1.5; color: #727d91; }
        .city-logo, .health-logo { position: absolute; top: 0.3mm; width: 5.5mm; height: auto; }
        .city-logo { right: 8mm; }
        .health-logo { right: 0; }
        .card-information { position: absolute; left: 3mm; top: 16mm; width: 53mm; }
        .patient-name { margin: 0 0 1.5mm; font-weight: bold; font-size: {{ mb_strlen($fullName) > 55 ? '6.5' : '8.5' }}pt; line-height: 1.3; overflow-wrap: anywhere; word-wrap: break-word; }
        .family-number { margin-bottom: 2mm; padding-bottom: 1.5mm; border-bottom: 0.2mm solid #e8eaf0; font-size: 7pt; font-weight: bold; }
        .detail { margin-bottom: 1mm; font-size: 6pt; line-height: 1.4; }
        .address { color: #727d91; font-size: {{ mb_strlen($patient->address ?? '') > 140 ? '4.5' : '5.5' }}pt; line-height: 1.3; overflow-wrap: anywhere; word-wrap: break-word; }
        .qr-panel { position: absolute; right: 3mm; top: 19mm; width: 23mm; text-align: center; }
        .qr-code { display: block; width: 23mm; height: 23mm; }
        .qr-caption { font-size: 4pt; color: #727d91; line-height: 1.3; }
        .card-footer { position: absolute; left: 3mm; right: 3mm; bottom: 2.5mm; font-size: 4pt; line-height: 1.4; color: #727d91; }
        @media print {
            html, body { width: 85.60mm; height: 53.98mm; }
            * { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <article class="health-card" aria-label="Patient identification card">
        <header class="card-header">
            <img class="brand-logo" src="{{ $logos['barangay-alabang'] }}" alt="Barangay Alabang">
            <div class="brand-copy"><div class="center-name">Alabang Health Center</div><div class="card-subtitle">City of Muntinlupa &middot; Healthcare ID</div></div>
            <img class="city-logo" src="{{ $logos['city-of-muntinlupa'] }}" alt="City of Muntinlupa">
            <img class="health-logo" src="{{ $logos['department-of-health'] }}" alt="Department of Health">
        </header>
        <div class="card-information">
            <h1 class="patient-name">{{ $fullName ?: 'Not available' }}</h1>
            <div class="family-number">{{ $familyNumber ?? 'Not assigned' }}</div>
            <div class="detail">Birthdate: {{ $birthdate }}</div>
            <div class="detail">Sex: {{ $patient->sex ?: 'Not available' }}</div>
            <div class="address">{{ $patient->address ?: 'Not available' }}</div>
        </div>
        <div class="qr-panel"><img class="qr-code" src="{{ $qrCode }}" alt="Patient identification QR code"><div class="qr-caption">Patient QR</div></div>
        <footer class="card-footer">{{ $familyNumber === null ? 'Complete the patient’s family association to assign a family number.' : 'Present this card when visiting the health center. QR identifies your individual patient record.' }}</footer>
    </article>
</body>
</html>
