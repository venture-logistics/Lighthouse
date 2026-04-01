<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

header('Content-Type: application/json');

// Get token
$stmt = $pdo->prepare("SELECT * FROM hmrc_tokens WHERE user_id = ?");
$stmt->execute([$user_id]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Not connected to HMRC']);
    exit;
}

// Refresh if expired
if (new DateTime() >= new DateTime($token['token_expires'])) {
    $token = refresh_hmrc_token($pdo, $token);
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'HMRC session expired. Please reconnect.']);
        exit;
    }
}

// Get VRN
$stmt_vat = $pdo->prepare("SELECT vat_number FROM vat_settings WHERE user_id = ?");
$stmt_vat->execute([$user_id]);
$vat_row = $stmt_vat->fetch(PDO::FETCH_ASSOC);
$vrn = preg_replace('/[^0-9]/', '', $vat_row['vat_number'] ?? '');

if (!$vrn) {
    echo json_encode(['success' => false, 'error' => 'No VAT Registration Number found']);
    exit;
}

// Get dates from the request, fallback to current year
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to']   ?? date('Y-12-31');

// Clamp to max 366 days just in case
$fromDt = new DateTime($from);
$toDt   = new DateTime($to);
if ($toDt->diff($fromDt)->days > 366) {
    $toDt = (clone $fromDt)->modify('+366 days');
    $to   = $toDt->format('Y-m-d');
}

$url = "https://test-api.service.hmrc.gov.uk/organisations/vat/{$vrn}/obligations?from={$from}&to={$to}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token['access_token'],
        'Accept: application/vnd.hmrc.1.0+json',
    ],
]);

$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_status !== 200) {
    echo json_encode([
        'success' => false,
        'error'   => 'HMRC error: ' . ($result['message'] ?? $response),
    ]);
    exit;
}

// TEMP DEBUG — add just before the obligations loop
echo json_encode([
    'success'      => true,
    'debug_raw'    => $result,
    'obligations'  => [],
]);
exit;

// Return only open obligations
$obligations = [];
foreach ($result['obligations'] ?? [] as $ob) {
    if ($ob['status'] === 'O') {
        $obligations[] = [
            'periodKey' => $ob['periodKey'],
            'start'     => $ob['start'],
            'end'       => $ob['end'],
            'due'       => $ob['due'],
            'label'     => date('d M Y', strtotime($ob['start']))
                         . ' — '
                         . date('d M Y', strtotime($ob['end']))
                         . ' (due ' . date('d M Y', strtotime($ob['due'])) . ')',
        ];
    }
}

echo json_encode(['success' => true, 'obligations' => $obligations]);
exit;