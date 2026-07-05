<?php

namespace App\Services;

use App\Models\Lot;
use Illuminate\Support\Str;

class QrCodeService
{
    private const VERSION = 2;

    // ── Génération ────────────────────────────────────────────────────────────

    /**
     * Génère un token signé pour un lot.
     *
     * Structure du payload :
     *   v          — version du schéma token
     *   lot_id     — identifiant unique du lot
     *   ref        — référence lisible (LOT-YYYY-NNNN)
     *   amb        — ambassade_id cible
     *   iat        — timestamp d'émission
     *   exp        — timestamp d'expiration
     *   nonce      — aléatoire 24 chars (prévient le replay d'un token identique)
     *   ch         — content_hash : HMAC des données critiques du lot au moment de l'émission
     *
     * Le token final est : base64url(payload) . "." . HMAC-SHA256(base64url(payload), secret)
     */
    public function generateToken(Lot $lot): string
    {
        $secret = $this->secret();

        $payload = [
            'v'      => self::VERSION,
            'lot_id' => $lot->id,
            'ref'    => $lot->reference,
            'amb'    => $lot->ambassade_id,
            'iat'    => now()->timestamp,
            'exp'    => now()->addMonths((int) config('app.qr_token_expiry_months', 6))->timestamp,
            'nonce'  => Str::random(24),
            'ch'     => $this->contentHash($lot, $secret),
        ];

        $data = $this->encodePayload($payload);
        $sig  = hash_hmac('sha256', $data, $secret);

        return "{$data}.{$sig}";
    }

    /**
     * Génère le QR code en SVG (aucune extension PHP requise).
     * Retourne le markup SVG brut.
     */
    public function generateQrImage(string $token): string
    {
        $url = $this->scanUrl($token);

        return \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(300)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($url);
    }

    /**
     * Génère le QR code en PNG via GD (sans imagick).
     * Retourne les bytes binaires PNG.
     */
    public function generateQrPng(string $token, int $size = 300): string
    {
        $url     = $this->scanUrl($token);
        $encoder = new \BaconQrCode\Encoder\Encoder();
        $qr      = $encoder->encode($url, \BaconQrCode\Common\ErrorCorrectionLevel::H());
        $matrix  = $qr->getMatrix();
        $modules = $matrix->getWidth();

        $margin     = 4;
        $moduleSize = (int) floor(($size - $margin * 2) / $modules);
        $imgSize    = $modules * $moduleSize + $margin * 2;

        $img   = imagecreate($imgSize, $imgSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        imagefill($img, 0, 0, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = $margin + $x * $moduleSize;
                    $py = $margin + $y * $moduleSize;
                    imagefilledrectangle($img, $px, $py, $px + $moduleSize - 1, $py + $moduleSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    // ── Vérification ─────────────────────────────────────────────────────────

    /**
     * Vérifie le token et retourne le payload si valide, null sinon.
     * Compatible avec l'API utilisée dans LotController::qrCode().
     */
    public function verifyToken(string $token): ?array
    {
        $result = $this->verifyTokenWithDetails($token);
        return $result['valid'] ? $result['payload'] : null;
    }

    /**
     * Vérification complète avec code d'erreur.
     *
     * Codes d'erreur possibles :
     *   INVALID_FORMAT    — token mal formé (pas 2 parties)
     *   INVALID_SIGNATURE — HMAC ne correspond pas (falsification)
     *   INVALID_PAYLOAD   — base64/JSON invalide
     *   EXPIRED           — token expiré (champ exp dépassé)
     *   UNKNOWN_VERSION   — version du schéma non supportée
     *
     * @return array{valid: bool, error_code: ?string, error_message: ?string, payload: ?array}
     */
    public function verifyTokenWithDetails(string $token): array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return $this->invalid('INVALID_FORMAT', 'Format de token invalide (séparateur manquant).');
        }

        [$data, $sig] = $parts;
        $secret        = $this->secret();
        $expected      = hash_hmac('sha256', $data, $secret);

        // Comparaison en temps constant — résiste aux timing attacks
        if (! hash_equals($expected, $sig)) {
            return $this->invalid('INVALID_SIGNATURE', 'Signature invalide : token falsifié ou clé incorrecte.');
        }

        $payload = $this->decodePayload($data);

        if (! $payload) {
            return $this->invalid('INVALID_PAYLOAD', 'Payload illisible : token corrompu.');
        }

        if (! isset($payload['v']) || $payload['v'] > self::VERSION) {
            return $this->invalid('UNKNOWN_VERSION', "Version de token non supportée ({$payload['v']}).");
        }

        if (! isset($payload['exp']) || $payload['exp'] < now()->timestamp) {
            $expiredAt = isset($payload['exp'])
                ? now()->createFromTimestamp($payload['exp'])->toDateString()
                : 'inconnue';

            return $this->invalid('EXPIRED', "Token expiré le {$expiredAt}.");
        }

        return ['valid' => true, 'error_code' => null, 'error_message' => null, 'payload' => $payload];
    }

    /**
     * Vérifie que le content_hash du token correspond toujours aux données actuelles du lot.
     * Retourne false si le lot a changé depuis la génération du QR.
     */
    public function verifyContentHash(string $token, Lot $lot): bool
    {
        $payload = $this->verifyToken($token);

        if (! $payload || ! isset($payload['ch'])) {
            return false;
        }

        return hash_equals($payload['ch'], $this->contentHash($lot, $this->secret()));
    }

    // ── Helpers publics ───────────────────────────────────────────────────────

    /**
     * URL frontale encodant le token (cible du QR code).
     */
    public function scanUrl(string $token): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/reception/scan/' . urlencode($token);
    }

    /**
     * Vérifie si le token expire dans moins de N jours.
     */
    public function expiresWithinDays(string $token, int $days = 30): bool
    {
        $payload = $this->verifyToken($token);
        if (! $payload) {
            return false;
        }

        return $payload['exp'] < now()->addDays($days)->timestamp;
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    /**
     * Hash des données critiques du lot au moment de la génération.
     * Lie cryptographiquement le QR aux données lot (ambassade, référence).
     * Si quelqu'un modifie lot_id ou ambassade_id après génération, le hash diffère.
     */
    private function contentHash(Lot $lot, string $secret): string
    {
        $passeportsCount = $lot->passeports()->count();

        $critical = json_encode([
            'lot_id'          => $lot->id,
            'reference'       => $lot->reference,
            'ambassade_id'    => $lot->ambassade_id,
            'passeports_count'=> $passeportsCount,
        ]);

        return hash_hmac('sha256', $critical, $secret);
    }

    private function encodePayload(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    }

    private function decodePayload(string $data): ?array
    {
        $json = base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));

        return $json ? json_decode($json, true) : null;
    }

    private function secret(): string
    {
        $secret = config('app.qr_secret_key');

        if (! $secret || $secret === 'change_me') {
            throw new \RuntimeException('QR_SECRET_KEY not configured. Set it in .env.');
        }

        return $secret;
    }

    private function invalid(string $code, string $message): array
    {
        return ['valid' => false, 'error_code' => $code, 'error_message' => $message, 'payload' => null];
    }
}
