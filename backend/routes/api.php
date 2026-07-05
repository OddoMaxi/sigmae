<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\AmbassadeController;
use App\Http\Controllers\Api\TransporteurController;
use App\Http\Controllers\Api\PasseportController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\ReceptionController;
use App\Http\Controllers\Api\ReceptionMAEController;
use App\Http\Controllers\Api\AnomalieController;
use App\Http\Controllers\Api\ReportingController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\PaysController;
use App\Http\Controllers\Api\StockCentralController;
use App\Http\Controllers\Api\EnrolementController;
use Illuminate\Support\Facades\Route;

// ─── Auth (public) ──────────────────────────────────────────────────────────
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ─── Scan QR (public — token signé, validation uniquement, sans données sensibles) ─
Route::get('reception/scan/{token}', [ReceptionController::class, 'scan'])
    ->middleware('throttle:60,1');

// ─── Routes protégées (Sanctum) ──────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me',       [AuthController::class, 'me']);
    });

    // ── Utilisateurs ────────────────────────────────────────────────────────
    Route::middleware('permission:users.view')->group(function () {
        Route::get('users',          [UserController::class, 'index']);
        Route::get('users/{user}',   [UserController::class, 'show']);
    });
    Route::middleware('permission:users.create')->post('users',          [UserController::class, 'store']);
    Route::middleware('permission:users.update')->put('users/{user}',    [UserController::class, 'update']);
    Route::middleware('permission:users.delete')->delete('users/{user}', [UserController::class, 'destroy']);
    Route::middleware('permission:users.toggle_status')->patch(
        'users/{user}/toggle-status', [UserController::class, 'toggleStatus']
    );

    // ── Rôles & permissions ─────────────────────────────────────────────────
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('roles',              [RoleController::class, 'index']);
        Route::get('roles/{role}',       [RoleController::class, 'show']);
        Route::get('permissions',        [PermissionController::class, 'index']);
    });
    Route::middleware('permission:roles.manage')->group(function () {
        Route::post('roles',                              [RoleController::class, 'store']);
        Route::put('roles/{role}/permissions',            [RoleController::class, 'updatePermissions']);
        Route::delete('roles/{role}',                    [RoleController::class, 'destroy']);
    });

    // ── Pays ────────────────────────────────────────────────────────────────
    Route::middleware('permission:pays.view')->group(function () {
        Route::get('pays',        [PaysController::class, 'index']);
        Route::get('pays/{pays}', [PaysController::class, 'show']);
    });
    Route::middleware('permission:pays.create')->post('pays',            [PaysController::class, 'store']);
    Route::middleware('permission:pays.update')->put('pays/{pays}',      [PaysController::class, 'update']);
    Route::middleware('permission:pays.delete')->delete('pays/{pays}',   [PaysController::class, 'destroy']);

    // ── Ambassades ──────────────────────────────────────────────────────────
    Route::middleware('permission:ambassades.view')->group(function () {
        Route::get('ambassades',              [AmbassadeController::class, 'index']);
        Route::get('ambassades/{ambassade}',  [AmbassadeController::class, 'show']);
    });
    Route::middleware('permission:ambassades.create')->post('ambassades',           [AmbassadeController::class, 'store']);
    Route::middleware('permission:ambassades.update')->put('ambassades/{ambassade}',[AmbassadeController::class, 'update']);

    // ── Transporteurs ────────────────────────────────────────────────────────
    Route::middleware('permission:transporteurs.view')->group(function () {
        Route::get('transporteurs',               [TransporteurController::class, 'index']);
        Route::get('transporteurs/{transporteur}',[TransporteurController::class, 'show']);
    });
    Route::middleware('permission:transporteurs.create')->post('transporteurs',                               [TransporteurController::class, 'store']);
    Route::middleware('permission:transporteurs.update')->put('transporteurs/{transporteur}',               [TransporteurController::class, 'update']);
    Route::middleware('permission:transporteurs.update')->patch('transporteurs/{transporteur}/toggle-status',[TransporteurController::class, 'toggleStatus']);
    Route::middleware('permission:transporteurs.delete')->delete('transporteurs/{transporteur}',             [TransporteurController::class, 'destroy']);

    // ── Enrôlements (premier niveau du circuit — ambassade) ──────────────────
    Route::prefix('enrolements')->group(function () {
        Route::middleware('permission:enrolement.view')->group(function () {
            Route::get('/',            [EnrolementController::class, 'index']);
            Route::get('/{passeport}', [EnrolementController::class, 'show']);
        });
        Route::middleware('permission:enrolement.create')->group(function () {
            Route::post('/', [EnrolementController::class, 'store']);
        });
        Route::middleware('permission:enrolement.assign_numero')->group(function () {
            Route::post('/{passeport}/assigner-numero', [EnrolementController::class, 'assignerNumero']);
        });
    });

    // ── Passeports ───────────────────────────────────────────────────────────
    Route::middleware('permission:passeports.view')->prefix('passeports')->group(function () {
        Route::get('/',                           [PasseportController::class, 'index']);
        Route::get('/stock',                      [PasseportController::class, 'stock']);
        Route::get('/check-doublon',              [PasseportController::class, 'checkDoublon']);
        Route::get('/{passeport}',                [PasseportController::class, 'show']);
        Route::get('/{passeport}/historique',     [PasseportController::class, 'historique']);
    });
    Route::middleware('permission:passeports.create')->group(function () {
        Route::post('passeports',                                         [PasseportController::class, 'store']);
        Route::post('passeports/{passeport}/receptionner-mae',            [PasseportController::class, 'receptionnerMAE']);
        Route::post('passeports/{passeport}/mettre-en-stock',             [PasseportController::class, 'mettreEnStock']);
    });
    Route::middleware('permission:passeports.import')->post('passeports/import', [PasseportController::class, 'import']);
    Route::middleware('permission:passeports.update')->put('passeports/{passeport}', [PasseportController::class, 'update']);
    // Transitions ambassade (permission lots.receive)
    Route::middleware('permission:lots.receive')->group(function () {
        Route::post('passeports/{passeport}/disponible-retrait', [PasseportController::class, 'disponibleRetrait']);
        Route::post('passeports/{passeport}/remettre-citoyen',   [PasseportController::class, 'remettreAuCitoyen']);
    });

    // ── Réception MAE ────────────────────────────────────────────────────────
    Route::prefix('reception-mae')->middleware('permission:passeports.create')->group(function () {
        // Lecture seule (statistiques & liste)
        Route::get('/recap',          [ReceptionMAEController::class, 'recap']);
        Route::get('/aujourd-hui',    [ReceptionMAEController::class, 'aujourdHui']);
        Route::get('/statistiques',   [ReceptionMAEController::class, 'statistiques']);
        // Actions
        Route::post('/scanner',       [ReceptionMAEController::class, 'scanner']);
        Route::post('/valider',       [ReceptionMAEController::class, 'valider']);
        Route::post('/mettre-en-stock',[ReceptionMAEController::class, 'mettreEnStock']);
        Route::post('/batch',         [ReceptionMAEController::class, 'batch']);
    });

    // ── Lots ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:lots.view')->prefix('lots')->group(function () {
        Route::get('/',                   [LotController::class, 'index']);
        Route::get('/{lot}',              [LotController::class, 'show']);
        Route::get('/{lot}/bordereau',    [LotController::class, 'bordereau']);
        Route::get('/{lot}/passeports',   [LotController::class, 'passeports']);
        Route::get('/{lot}/historique',   [LotController::class, 'historique']);
        Route::get('/{lot}/qr-code',      [LotController::class, 'qrCode']);
    });
    Route::middleware('permission:lots.create')->post('lots',                         [LotController::class, 'store']);
    Route::middleware('permission:lots.update')->group(function () {
        Route::put('lots/{lot}',                            [LotController::class, 'update']);
        Route::post('lots/{lot}/passeports',                [LotController::class, 'addPasseports']);
        Route::delete('lots/{lot}/passeports/{passeport}',  [LotController::class, 'removePasseport']);
    });
    Route::middleware('permission:lots.delete')->delete('lots/{lot}',        [LotController::class, 'destroy']);
    Route::middleware('permission:lots.validate')->post('lots/{lot}/valider', [LotController::class, 'valider']);
    Route::middleware('permission:lots.ship')->post('lots/{lot}/expedier',    [LotController::class, 'expedier']);

    // ── Réception ambassade ──────────────────────────────────────────────────
    Route::middleware('permission:lots.receive')->prefix('reception')->group(function () {
        // ── Consultation ──────────────────────────────────────────────────
        Route::get('/detail/{token}',                    [ReceptionController::class, 'detail']);
        Route::get('/lot/{lot}/historique',              [ReceptionController::class, 'historiqueReception']);

        // ── Confirmation batch (par token QR) ─────────────────────────────
        Route::post('/lot/{token}',                      [ReceptionController::class, 'confirmerLot']);

        // ── Actions sur un lot identifié ─────────────────────────────────
        Route::post('/lot/{lot}/disponible',             [ReceptionController::class, 'passerDisponible']);
        Route::post('/lot/{lot}/commentaire',            [ReceptionController::class, 'commentaireAmbassade']);

        // ── Actions passeport par passeport ───────────────────────────────
        Route::post('/lot-detail/{lot}/passeport/{passeport}',          [ReceptionController::class, 'confirmerPasseport']);
        Route::post('/lot-detail/{lot}/passeport/{passeport}/anomalie', [ReceptionController::class, 'signalerAnomalie']);
        Route::post('/lot-detail/{lot}/passeport/{passeport}/manquant', [ReceptionController::class, 'marquerManquant']);
    });

    // ── Anomalies ────────────────────────────────────────────────────────────
    Route::middleware('permission:anomalies.view')->group(function () {
        Route::get('anomalies',            [AnomalieController::class, 'index']);
        Route::get('anomalies/{anomalie}', [AnomalieController::class, 'show']);
    });
    Route::middleware('permission:anomalies.create')->post('anomalies',                        [AnomalieController::class, 'store']);
    Route::middleware('permission:anomalies.update')->put('anomalies/{anomalie}',              [AnomalieController::class, 'update']);
    Route::middleware('permission:anomalies.resolve')->post('anomalies/{anomalie}/resoudre',   [AnomalieController::class, 'resoudre']);

    // ── Reporting ────────────────────────────────────────────────────────────
    Route::middleware('permission:reporting.view')->prefix('reporting')->group(function () {
        Route::get('/dashboard',    [ReportingController::class, 'dashboard']);
        Route::get('/expeditions',  [ReportingController::class, 'expeditions']);
        Route::get('/receptions',   [ReportingController::class, 'receptions']);
        Route::get('/anomalies',    [ReportingController::class, 'anomalies']);
        Route::get('/performance',  [ReportingController::class, 'performance']);
    });

    // ── Export ────────────────────────────────────────────────────────────────
    Route::middleware('permission:reporting.export')->prefix('export')->group(function () {
        Route::get('/passeports', [ExportController::class, 'passeports']);
        Route::get('/lots',       [ExportController::class, 'lots']);
        Route::get('/anomalies',  [ExportController::class, 'anomalies']);
    });

    // ── Stock Central MAE ─────────────────────────────────────────────────────
    Route::middleware('permission:passeports.view')->prefix('stock-central')->group(function () {
        Route::get('/',           [StockCentralController::class, 'index']);
        Route::get('/inventaire', [StockCentralController::class, 'inventaire']);
        Route::get('/alertes',    [StockCentralController::class, 'alertes']);
    });
    Route::middleware('permission:reporting.export')
        ->get('stock-central/export', [StockCentralController::class, 'export']);

    // ── Audit ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit',                          [AuditController::class, 'index']);
        Route::get('audit/{entityType}/{entityId}',  [AuditController::class, 'entity']);
    });
});
