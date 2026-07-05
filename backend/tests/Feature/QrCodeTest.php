<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Config;

class QrCodeTest extends SgpTestCase
{
    private QrCodeService $qr;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.qr_secret_key', 'test_qr_secret_key_sgpge');
        Config::set('app.qr_token_expiry_months', 6);
        Config::set('app.frontend_url', 'http://localhost:3000');
        $this->qr = app(QrCodeService::class);
    }

    // ── Génération ────────────────────────────────────────────────────────────

    public function test_generation_token_contient_payload_correct(): void
    {
        $user = $this->makeAdminCentral();
        $lot  = $this->makeLot($user, $this->ambassade);

        $token = $this->qr->generateToken($lot);
        $this->assertNotEmpty($token);

        $payload = $this->qr->verifyToken($token);
        $this->assertNotNull($payload);
        $this->assertEquals($lot->id, $payload['lot_id']);
        $this->assertEquals($lot->reference, $payload['ref']);
        $this->assertEquals($this->ambassade->id, $payload['amb']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('nonce', $payload);
        $this->assertArrayHasKey('ch', $payload);
    }

    public function test_token_expire_dans_6_mois(): void
    {
        $user    = $this->makeAdminCentral();
        $lot     = $this->makeLot($user, $this->ambassade);
        $token   = $this->qr->generateToken($lot);
        $payload = $this->qr->verifyToken($token);

        $expiresIn = $payload['exp'] - now()->timestamp;
        $this->assertGreaterThan(0, $expiresIn, 'Token doit expirer dans le futur');
        // Environ 6 mois (entre 5 et 7 mois en secondes)
        $this->assertGreaterThan(60 * 60 * 24 * 150, $expiresIn);
        $this->assertLessThan(60 * 60 * 24 * 210, $expiresIn);
    }

    // ── Vérification ─────────────────────────────────────────────────────────

    public function test_token_valide_retourne_payload(): void
    {
        $user  = $this->makeAdminCentral();
        $lot   = $this->makeLot($user, $this->ambassade);
        $token = $this->qr->generateToken($lot);

        $result = $this->qr->verifyTokenWithDetails($token);
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error_code']);
        $this->assertNotNull($result['payload']);
    }

    public function test_token_falsifie_invalide(): void
    {
        $user  = $this->makeAdminCentral();
        $lot   = $this->makeLot($user, $this->ambassade);
        $token = $this->qr->generateToken($lot);

        // Modifier le dernier caractère de la signature
        $tampered = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');

        $result = $this->qr->verifyTokenWithDetails($tampered);
        $this->assertFalse($result['valid']);
        $this->assertEquals('INVALID_SIGNATURE', $result['error_code']);
    }

    public function test_token_mal_forme_invalide(): void
    {
        $result = $this->qr->verifyTokenWithDetails('not-a-valid-token-no-dot');
        $this->assertFalse($result['valid']);
        $this->assertEquals('INVALID_FORMAT', $result['error_code']);
    }

    public function test_token_expire_invalide(): void
    {
        $user = $this->makeAdminCentral();
        $lot  = $this->makeLot($user, $this->ambassade);

        // Générer un token expiré (exp dans le passé)
        Config::set('app.qr_token_expiry_months', -1); // "expire" dans -1 mois = déjà expiré
        $token = $this->qr->generateToken($lot);

        // Vérification de l'expiration — le payload doit avoir exp < now
        // Inspecter manuellement le payload pour confirmer exp passé
        $parts = explode('.', $token, 2);
        $json  = base64_decode(strtr($parts[0], '-_', '+/') . '===');
        $payload = json_decode($json, true);

        // Recalculer la signature pour un token avec exp passé
        $secret   = config('app.qr_secret_key');
        $expected = hash_hmac('sha256', $parts[0], $secret);
        $this->assertEquals($expected, $parts[1], 'Le token doit être structurellement valide');

        // Vérification complète doit retourner EXPIRED
        $result = $this->qr->verifyTokenWithDetails($token);
        $this->assertFalse($result['valid']);
        $this->assertEquals('EXPIRED', $result['error_code']);
    }

    // ── Content hash ─────────────────────────────────────────────────────────

    public function test_content_hash_invalide_si_lot_modifie(): void
    {
        $user  = $this->makeAdminCentral();
        $lot   = $this->makeLot($user, $this->ambassade);
        $token = $this->qr->generateToken($lot);

        // Changer l'ambassade du lot APRÈS génération du token
        $lot->update(['ambassade_id' => $this->autreAmbassade->id]);

        $this->assertFalse($this->qr->verifyContentHash($token, $lot->fresh()));
    }

    public function test_content_hash_valide_si_lot_inchange(): void
    {
        $user  = $this->makeAdminCentral();
        $lot   = $this->makeLot($user, $this->ambassade);
        $token = $this->qr->generateToken($lot);

        $this->assertTrue($this->qr->verifyContentHash($token, $lot));
    }

    // ── Endpoint API scan public ──────────────────────────────────────────────

    public function test_scan_public_token_valide(): void
    {
        Config::set('app.frontend_url', 'http://localhost:3000');
        $user  = $this->makeAdminCentral();
        $lot   = $this->makeLot($user, $this->ambassade, ['statut' => 'expedie']);
        $token = $this->qr->generateToken($lot);
        $lot->update(['qr_token' => $token]);

        $this->getJson('/api/reception/scan/' . urlencode($token))
            ->assertOk()
            ->assertJsonFragment(['valid' => true])
            ->assertJsonFragment(['reference' => $lot->reference]);
    }

    public function test_scan_public_token_falsifie(): void
    {
        $this->getJson('/api/reception/scan/fakepayload.fakesignature')
            ->assertBadRequest()
            ->assertJsonFragment(['valid' => false]);
    }

    public function test_scan_public_ne_retourne_pas_donnees_sensibles(): void
    {
        $user  = $this->makeAdminCentral();
        $lot   = $this->makeLot($user, $this->ambassade, ['statut' => 'expedie']);
        $token = $this->qr->generateToken($lot);
        $lot->update(['qr_token' => $token]);

        $body = $this->getJson('/api/reception/scan/' . urlencode($token))->json();

        // L'endpoint public ne doit PAS retourner la liste des passeports
        $this->assertArrayNotHasKey('passeports', $body);
    }
}
