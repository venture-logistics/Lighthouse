<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// ── Read JSON body ────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid request body']);
    exit;
}

// ── Get HMRC token ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM hmrc_tokens WHERE user_id = ?");
$stmt->execute([$user_id]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Not connected to HMRC']);
    exit;
}

// ── Refresh token if expired ──────────────────────────────────────────────────
if (new DateTime() >= new DateTime($token['token_expires'])) {
    $token = refresh_hmrc_token($pdo, $token);
    if (!$token) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'HMRC session expired. Please reconnect.']);
        exit;
    }
}

$access_token = $token['access_token'];

// ── Get VRN from vat_settings ─────────────────────────────────────────────────
$stmt_vat = $pdo->prepare("SELECT vat_number FROM vat_settings WHERE user_id = ?");
$stmt_vat->execute([$user_id]);
$vat_row = $stmt_vat->fetch(PDO::FETCH_ASSOC);
$vrn = preg_replace('/[^0-9]/', '', $vat_row['vat_number'] ?? '');

if (!$vrn) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'No VAT Registration Number found.']);
    exit;
}

// ── Build payload ─────────────────────────────────────────────────────────────
$payload = [
    'periodKey'                       => $input['periodKey'],
    'vatDueSales'                     => round((float)$input['box1'], 2),
    'vatDueAcquisitions'              => round((float)$input['box2'], 2),
    'totalVatDue'                     => round((float)$input['box3'], 2),
    'vatReclaimedCurrPeriod'          => round((float)$input['box4'], 2),
    'netVatDue'                       => round(abs((float)$input['box5']), 2),
    'totalValueSalesExVAT'            => (int)round((float)$input['box6']),
    'totalValuePurchasesExVAT'        => (int)round((float)$input['box7']),
    'totalValueGoodsSuppliedExVAT'    => (int)round((float)$input['box8']),
    'totalAcquisitionsExVAT'          => (int)round((float)$input['box9']),
    'finalised'                       => true,
];

// ── Call HMRC API ─────────────────────────────────────────────────────────────
$url = "https://test-api.service.hmrc.gov.uk/organisations/vat/{$vrn}/returns";

error_log("HMRC VAT URL: " . $url);
error_log("HMRC VAT Payload: " . json_encode($payload));

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
        'Accept: application/vnd.hmrc.1.0+json',
    ],
]);

$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

error_log("HMRC VAT Response [{$http_status}]: " . $response);

// ── Handle cURL error ─────────────────────────────────────────────────────────
if ($curl_error) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Connection error: ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);

// ── Success ───────────────────────────────────────────────────────────────────
if ($http_status === 201) {
    $stmt = $pdo->prepare("
        INSERT INTO vat_submissions 
            (user_id, period_start, period_end, box1, box2, box3, box4, box5, 
             box6, box7, box8, box9, form_bundle_number, processing_date, submitted_at)
        VALUES 
            (:uid, :start, :end, :b1, :b2, :b3, :b4, :b5, 
             :b6, :b7, :b8, :b9, :fbn, :pd, NOW())
    ");
    $stmt->execute([
        ':uid'   => $user_id,
        ':start' => $input['from'],
        ':end'   => $input['to'],
        ':b1'    => $payload['vatDueSales'],
        ':b2'    => $payload['vatDueAcquisitions'],
        ':b3'    => $payload['totalVatDue'],
        ':b4'    => $payload['vatReclaimedCurrPeriod'],
        ':b5'    => $payload['netVatDue'],
        ':b6'    => $payload['totalValueSalesExVAT'],
        ':b7'    => $payload['totalValuePurchasesExVAT'],
        ':b8'    => $payload['totalValueGoodsSuppliedExVAT'],
        ':b9'    => $payload['totalAcquisitionsExVAT'],
        ':fbn'   => $result['formBundleNumber'] ?? null,
        ':pd'    => $result['processingDate']   ?? null,
    ]);

    ob_clean();
    echo json_encode([
        'success'          => true,
        'processingDate'   => $result['processingDate']   ?? '',
        'formBundleNumber' => $result['formBundleNumber'] ?? '',
    ]);
    exit;
}

// ── HMRC returned an error ────────────────────────────────────────────────────
$error_msg = $result['message']
    ?? $result['errors'][0]['message']
    ?? $response
    ?? 'HMRC rejected the submission';

error_log("HMRC VAT submission error [{$http_status}]: " . $response);

ob_clean();
echo json_encode([
    'success' => false,
    'error'   => "HMRC Error ({$http_status}): {$error_msg}",
]);
exit;