<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 10.5px;
        color: #23343f;
        -webkit-font-smoothing: antialiased;
    }

    .page { padding: 18mm 16mm 14mm 16mm; }

    /* ── En-tête ─────────────────────────────────────────────────────── */

    .header { display: table; width: 100%; }
    .header-left { display: table-cell; vertical-align: top; width: 60px; padding-top: 2px; }
    .header-left img { width: 42px; height: 42px; border-radius: 50%; }
    .header-text { display: table-cell; vertical-align: top; }
    .header-text .country { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #7c8a94; }
    .header-text h1 { font-size: 12px; line-height: 1.35; color: #1a5276; font-weight: bold; margin-top: 2px; }
    .header-text p { font-size: 9px; color: #7c8a94; margin-top: 3px; }
    .header-right { display: table-cell; vertical-align: top; text-align: right; font-size: 8.5px; color: #7c8a94; line-height: 1.5; white-space: nowrap; }
    .header-right b { color: #45535c; }

    .rule { height: 2px; background: #1a5276; margin-top: 12px; }
    .rule-accent { height: 2px; width: 46px; background: #c99a2e; margin-top: 2px; }

    /* ── Titre document ──────────────────────────────────────────────── */

    .doctitle { text-align: center; margin: 22px 0 20px; }
    .doctitle .eyebrow { font-size: 8.5px; letter-spacing: 2.5px; text-transform: uppercase; color: #c99a2e; font-weight: bold; }
    .doctitle h2 { font-size: 19px; letter-spacing: 0.5px; color: #1a2c37; margin-top: 4px; font-weight: bold; }
    .doctitle .ref { font-size: 10.5px; color: #7c8a94; margin-top: 3px; letter-spacing: 0.5px; }

    /* ── Bandeau d'informations ──────────────────────────────────────── */

    .meta { display: table; width: 100%; margin-bottom: 20px; table-layout: fixed; }
    .meta-col { display: table-cell; vertical-align: top; padding-right: 18px; width: 25%; }
    .meta-col.meta-col-total { width: 15%; text-align: right; padding-right: 0; }
    .meta-label { font-size: 7.8px; letter-spacing: 1.2px; text-transform: uppercase; color: #9aa7ae; margin-bottom: 3px; }
    .meta-value { font-size: 11.5px; color: #1a2c37; font-weight: bold; }
    .meta-sub { font-size: 9px; color: #7c8a94; margin-top: 1px; }
    .meta-divider { border: none; border-top: 1px solid #e6e9eb; margin: 14px 0; }

    .count-badge { color: #1a5276; font-size: 22px; font-weight: bold; line-height: 1.2; }

    /* ── Tableau des passeports ──────────────────────────────────────── */

    .section-label {
        font-size: 8.5px; letter-spacing: 1.5px; text-transform: uppercase;
        color: #9aa7ae; margin-bottom: 8px;
    }

    table { width: 100%; border-collapse: collapse; }
    thead th {
        text-align: left; font-size: 8px; letter-spacing: 0.8px; text-transform: uppercase;
        color: #1a5276; padding: 0 8px 7px 8px; border-bottom: 1.5px solid #1a5276;
    }
    thead th.num { width: 24px; }
    tbody td { padding: 7px 8px; font-size: 10px; border-bottom: 0.75px solid #e6e9eb; color: #33434c; }
    tbody tr:last-child td { border-bottom: 1px solid #d7dde0; }
    tbody td.num { color: #9aa7ae; }
    tbody td strong { color: #1a2c37; letter-spacing: 0.3px; }

    /* ── QR & vérification ────────────────────────────────────────────── */

    .verif { margin-top: 26px; display: table; width: 100%; }
    .qr-frame { display: table-cell; width: 104px; vertical-align: top; border: 1px solid #e6e9eb; border-radius: 6px; padding: 8px; }
    .qr-frame img { width: 88px; height: 88px; display: block; }
    .verif-text { display: table-cell; vertical-align: top; padding-left: 20px; }
    .verif-text .verif-title { font-size: 10.5px; font-weight: bold; color: #1a2c37; }
    .verif-text p { font-size: 9px; color: #7c8a94; line-height: 1.55; margin-top: 4px; max-width: 340px; }
    .verif-text .verif-ref { font-size: 8.5px; color: #9aa7ae; margin-top: 6px; letter-spacing: 0.3px; }

    /* ── Notes ────────────────────────────────────────────────────────── */

    .notes {
        margin-top: 18px; padding: 10px 14px; border-left: 2px solid #c99a2e;
        background: #fbf9f4; font-size: 9.5px; color: #5c4a1f; line-height: 1.5;
    }
    .notes b { color: #1a2c37; }

    /* ── Signatures ───────────────────────────────────────────────────── */

    .signatures { display: table; width: 100%; margin-top: 40px; }
    .sig-box { display: table-cell; width: 33.33%; padding-right: 20px; }
    .sig-box:last-child { padding-right: 0; }
    .sig-line { border-top: 0.75px solid #b8c0c5; padding-top: 7px; }
    .sig-role { font-size: 9px; font-weight: bold; color: #1a2c37; letter-spacing: 0.3px; }
    .sig-sub { font-size: 8px; color: #9aa7ae; margin-top: 1px; }

    /* ── Pied de page ─────────────────────────────────────────────────── */

    .footer {
        margin-top: 30px; padding-top: 10px; border-top: 0.75px solid #e6e9eb;
        text-align: center; font-size: 7.8px; letter-spacing: 0.4px; color: #aab3b8;
    }

    /* ── Filigrane ────────────────────────────────────────────────────── */

    .watermark {
        position: fixed;
        top: 103mm; left: 60mm;
        width: 90mm;
        opacity: 0.06;
        z-index: -1;
    }
    .watermark img { width: 100%; }
</style>
</head>
<body>
<div class="watermark"><img src="{{ public_path('images/guinee-watermark.jpeg') }}"></div>
<div class="page">

    <div class="header">
        <div class="header-left">
            <img src="{{ public_path('images/logo-maeiage.jpg') }}" alt="MAEIAGE">
        </div>
        <div class="header-text">
            <div class="country">République de Guinée</div>
            <h1>Ministère des Affaires Étrangères, de l&rsquo;Intégration Africaine et des Guinéens Établis à l&rsquo;Étranger</h1>
            <p>Cellule de Gestion des Passeports — SGP-GE</p>
        </div>
        <div class="header-right">
            <div>Généré le <b>{{ now()->format('d/m/Y') }}</b> à {{ now()->format('H:i') }}</div>
            <div>Par <b>{{ $lot->createdBy?->name ?? 'Système' }}</b></div>
        </div>
    </div>
    <div class="rule"></div>
    <div class="rule-accent"></div>

    <div class="doctitle">
        <div class="eyebrow">Document officiel de transport</div>
        <h2>Bordereau d&rsquo;Expédition</h2>
        <div class="ref">Référence {{ $lot->reference }}</div>
    </div>

    <div class="meta">
        <div class="meta-col">
            <div class="meta-label">Destination</div>
            <div class="meta-value">{{ $lot->ambassade->nom }}</div>
            <div class="meta-sub">{{ $lot->ambassade->ville }}, {{ $lot->ambassade->pays }}</div>
        </div>
        <div class="meta-col">
            <div class="meta-label">Transporteur</div>
            <div class="meta-value">{{ $lot->transporteur->nom }}</div>
            <div class="meta-sub">
                {{ $lot->transporteur->telephone }}
                @if($lot->reference_suivi) &middot; Suivi {{ $lot->reference_suivi }} @endif
            </div>
        </div>
        <div class="meta-col">
            <div class="meta-label">Expédition</div>
            <div class="meta-value">{{ $lot->date_expedition?->format('d/m/Y') ?? '—' }}</div>
            <div class="meta-sub">Réception prévue {{ $lot->date_reception_prevue?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="meta-col meta-col-total">
            <div class="meta-label">Total</div>
            <div class="count-badge">{{ $lot->passeports->count() }}</div>
            <div class="meta-sub">passeport{{ $lot->passeports->count() > 1 ? 's' : '' }}</div>
        </div>
    </div>
    <hr class="meta-divider">

    <div class="section-label">Contenu du lot</div>
    <table>
        <thead>
            <tr>
                <th class="num">#</th>
                <th>N° Passeport</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Date de naissance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lot->passeports as $i => $p)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td><strong>{{ $p->numero }}</strong></td>
                <td>{{ $p->nom_titulaire }}</td>
                <td>{{ $p->prenom_titulaire }}</td>
                <td>{{ $p->date_naissance?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="verif">
        <div class="qr-frame">
            <img src="{{ $qrDataUri }}" alt="QR Code">
        </div>
        <div class="verif-text">
            <div class="verif-title">Vérification à la réception</div>
            <p>
                L&rsquo;agent d&rsquo;ambassade scanne ce code à l&rsquo;arrivée du colis pour confirmer
                la réception et déclencher les notifications aux citoyens. Le code est signé
                numériquement — toute altération du contenu du lot l&rsquo;invalide automatiquement.
            </p>
            <div class="verif-ref">Réf. lot {{ $lot->reference }}</div>
        </div>
    </div>

    @if($lot->notes)
    <div class="notes"><b>Notes —</b> {{ $lot->notes }}</div>
    @endif

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">
                <div class="sig-role">Expéditeur</div>
                <div class="sig-sub">MAE — Cellule SGP-GE</div>
            </div>
        </div>
        <div class="sig-box">
            <div class="sig-line">
                <div class="sig-role">Transporteur</div>
                <div class="sig-sub">{{ $lot->transporteur->nom }}</div>
            </div>
        </div>
        <div class="sig-box">
            <div class="sig-line">
                <div class="sig-role">Réceptionnaire</div>
                <div class="sig-sub">{{ $lot->ambassade->nom }}</div>
            </div>
        </div>
    </div>

    <div class="footer">
        SGP-GE — SYSTÈME DE GESTION DES PASSEPORTS DES GUINÉENS ÉTABLIS À L&rsquo;ÉTRANGER &middot;
        DOCUMENT OFFICIEL — TOUTE FALSIFICATION EST PUNISSABLE PAR LA LOI
    </div>

</div>
</body>
</html>
