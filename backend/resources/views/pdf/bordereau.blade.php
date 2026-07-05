<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
    .page { padding: 20mm 15mm; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1a5276; padding-bottom: 12px; margin-bottom: 16px; }
    .header-text h1 { font-size: 15px; color: #1a5276; }
    .header-text p { font-size: 10px; color: #555; }
    .title { text-align: center; margin: 12px 0; }
    .title h2 { font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
    .meta-grid { display: flex; gap: 16px; margin: 12px 0; }
    .meta-box { flex: 1; border: 1px solid #ccc; padding: 8px 12px; border-radius: 4px; }
    .meta-box .label { font-size: 9px; color: #777; text-transform: uppercase; }
    .meta-box .value { font-size: 12px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    thead th { background: #1a5276; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
    tbody tr:nth-child(even) { background: #f5f8fb; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #ddd; font-size: 10px; }
    .qr-section { margin-top: 24px; display: flex; align-items: center; gap: 24px; border-top: 1px solid #ccc; padding-top: 16px; }
    .qr-section img { width: 120px; height: 120px; }
    .qr-text { font-size: 10px; color: #555; }
    .signatures { display: flex; justify-content: space-between; margin-top: 32px; }
    .sig-box { width: 200px; border-top: 1px solid #333; padding-top: 8px; text-align: center; font-size: 10px; }
    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .badge-expedie { background: #d4edda; color: #155724; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-text">
            <h1>République de Guinée</h1>
            <p>Ministère des Affaires Étrangères</p>
            <p>Cellule de Gestion des Passeports (SGP-GE)</p>
        </div>
        <div style="text-align:right;font-size:10px;color:#555;">
            <p>Généré le : {{ now()->format('d/m/Y à H:i') }}</p>
            <p>Par : {{ $lot->createdBy?->name ?? 'Système' }}</p>
        </div>
    </div>

    <div class="title">
        <h2>Bordereau d'Expédition</h2>
        <p style="color:#555;font-size:11px;">{{ $lot->reference }}</p>
    </div>

    <div class="meta-grid">
        <div class="meta-box">
            <div class="label">Destination</div>
            <div class="value">{{ $lot->ambassade->nom }}</div>
            <div style="font-size:10px;color:#555;">{{ $lot->ambassade->ville }}, {{ $lot->ambassade->pays }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Transporteur</div>
            <div class="value">{{ $lot->transporteur->nom }}</div>
            <div style="font-size:10px;color:#555;">{{ $lot->transporteur->telephone }}</div>
            @if($lot->reference_suivi)
            <div style="font-size:9px;color:#1a5276;margin-top:2px;">Réf. suivi : {{ $lot->reference_suivi }}</div>
            @endif
        </div>
        <div class="meta-box">
            <div class="label">Date d'expédition</div>
            <div class="value">{{ $lot->date_expedition?->format('d/m/Y') ?? '-' }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Réception prévue</div>
            <div class="value">{{ $lot->date_reception_prevue?->format('d/m/Y') ?? '-' }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Total passeports</div>
            <div class="value" style="color:#1a5276;font-size:18px;">{{ $lot->passeports->count() }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>N° Passeport</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Date de naissance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lot->passeports as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><strong>{{ $p->numero }}</strong></td>
                <td>{{ $p->nom_titulaire }}</td>
                <td>{{ $p->prenom_titulaire }}</td>
                <td>{{ $p->date_naissance?->format('d/m/Y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="qr-section">
        <img src="{{ $qrDataUri }}" alt="QR Code">
        <div class="qr-text">
            <p><strong>Scanner ce QR code</strong> lors de la réception en ambassade</p>
            <p style="margin-top:6px;">Ce code permet de confirmer la réception du lot<br>
            et d'envoyer automatiquement les notifications aux citoyens.</p>
            <p style="margin-top:6px;color:#999;">Référence : {{ $lot->reference }}</p>
        </div>
    </div>

    @if($lot->notes)
    <div style="margin-top:16px;padding:10px;background:#fffde7;border-left:3px solid #f9a825;font-size:10px;">
        <strong>Notes :</strong> {{ $lot->notes }}
    </div>
    @endif

    <div class="signatures">
        <div class="sig-box">
            Signature Expéditeur<br>
            <span style="color:#999;">(MAE – Cellule SGP)</span>
        </div>
        <div class="sig-box">
            Signature Transporteur<br>
            <span style="color:#999;">{{ $lot->transporteur->nom }}</span>
        </div>
        <div class="sig-box">
            Signature Réceptionnaire<br>
            <span style="color:#999;">(Ambassade de Guinée)</span>
        </div>
    </div>

    <div class="footer">
        SGP-GE • Système de Gestion des Passeports des Guinéens Établis à l'Étranger •
        Document officiel — toute falsification est punissable par la loi
    </div>
</div>
</body>
</html>
