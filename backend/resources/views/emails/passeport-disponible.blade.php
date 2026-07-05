<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .header { background: #1a5276; color: #fff; padding: 24px 32px; text-align: center; }
        .header img { height: 60px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 20px; }
        .body { padding: 32px; color: #333; }
        .info-box { background: #eaf4fb; border-left: 4px solid #1a5276; padding: 16px; margin: 24px 0; border-radius: 4px; }
        .info-box p { margin: 4px 0; }
        .footer { background: #f0f0f0; padding: 16px 32px; text-align: center; font-size: 12px; color: #777; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>République de Guinée</h1>
        <p style="margin:4px 0;font-size:13px;">Ministère des Affaires Étrangères</p>
        <p style="margin:4px 0;font-size:13px;">Cellule de Gestion des Passeports</p>
    </div>

    <div class="body">
        <p>Bonjour <strong>{{ $passeport->nom_complet }}</strong>,</p>

        <p>Nous avons le plaisir de vous informer que votre passeport est désormais disponible
           et peut être retiré auprès de l'ambassade / consulat de Guinée.</p>

        <div class="info-box">
            <p><strong>Numéro de passeport :</strong> {{ $passeport->numero }}</p>
            <p><strong>Titulaire :</strong> {{ $passeport->nom_complet }}</p>
            @if($passeport->ambassadeDestination)
            <p><strong>Lieu de retrait :</strong>
               Ambassade / Consulat de Guinée — {{ $passeport->ambassadeDestination->nom }}
               @if($passeport->ambassadeDestination->ville)({{ $passeport->ambassadeDestination->ville }})@endif
            </p>
            @endif
            <p><strong>Date de disponibilité :</strong> {{ ($passeport->disponible_at ?? now())->format('d/m/Y') }}</p>
        </div>

        <p>Veuillez vous présenter muni(e) d'une pièce d'identité valide pour récupérer votre document.</p>

        <p style="margin-top:32px;">Cordialement,</p>
        <p><strong>La Cellule de Gestion des Passeports</strong><br>
           Ministère des Affaires Étrangères<br>
           République de Guinée</p>
    </div>

    <div class="footer">
        <p>Ce message est envoyé automatiquement, merci de ne pas y répondre.</p>
        <p>© {{ date('Y') }} Ministère des Affaires Étrangères – République de Guinée</p>
    </div>
</div>
</body>
</html>
