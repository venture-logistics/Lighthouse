<?php
require_once __DIR__ . '/hmrc_config.php';

class HmrcClient
{
    private PDO $pdo;
    private int $user_id;

    public function __construct(PDO $pdo, int $user_id)
    {
        $this->pdo     = $pdo;
        $this->user_id = $user_id;
    }

    // ------------------------------------------------------------------
    // OAuth - build the URL to send the user to HMRC
    // ------------------------------------------------------------------
    public function getAuthUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['hmrc_oauth_state'] = $state;

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => HMRC_CLIENT_ID,
            'scope'         => 'read:vat write:vat',
            'state'         => $state,
            'redirect_uri'  => HMRC_REDIRECT_URI,
        ]);

        return 'https://test-www.tax.service.gov.uk/oauth/authorize?' . $params;
    }

    // ------------------------------------------------------------------
    // OAuth - exchange the code for tokens
    // ------------------------------------------------------------------
    public function exchangeCode(string $code): bool
    {
        $response = $this->post('/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => HMRC_REDIRECT_URI,
            'client_id'     => HMRC_CLIENT_ID,
            'client_secret' => HMRC_CLIENT_SECRET,
        ], false);

        if (empty($response['access_token'])) {
            return false;
        }

        $this->saveTokens($response);
        return true;
    }

    // ------------------------------------------------------------------
    // OAuth - refresh the access token
    // ------------------------------------------------------------------
    public function refreshToken(): bool
    {
        $tokens = $this->getStoredTokens();
        if (!$tokens || empty($tokens['refresh_token'])) {
            return false;
        }

        $response = $this->post('/oauth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
            'client_id'     => HMRC_CLIENT_ID,
            'client_secret' => HMRC_CLIENT_SECRET,
        ], false);

        if (empty($response['access_token'])) {
            return false;
        }

        $this->saveTokens($response);
        return true;
    }

    // ------------------------------------------------------------------
    // Check if we have a valid (non-expired) token
    // ------------------------------------------------------------------
    public function isConnected(): bool
    {
        $tokens = $this->getStoredTokens();
        if (!$tokens || empty($tokens['access_token'])) {
            return false;
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Get a valid access token - refresh if needed
    // ------------------------------------------------------------------
    public function getAccessToken(): ?string
    {
        $tokens = $this->getStoredTokens();
        if (!$tokens) {
            return null;
        }

        // If token expires in less than 60 seconds, refresh it
        if (strtotime($tokens['token_expires']) < (time() + 60)) {
            if (!$this->refreshToken()) {
                return null;
            }
            $tokens = $this->getStoredTokens();
        }

        return $tokens['access_token'];
    }

    // ------------------------------------------------------------------
    // Make an authenticated GET request to HMRC
    // ------------------------------------------------------------------
    public function get(string $endpoint): array
    {
        $accessToken = $this->getAccessToken();

        $ch = curl_init(HMRC_BASE_URL . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/vnd.hmrc.1.0+json',
            ],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body, true) ?? [];

        if ($httpCode !== 200) {
            throw new RuntimeException(
                'HMRC API error ' . $httpCode . ': ' . ($data['message'] ?? $body)
            );
        }

        return $data;
    }

    // ------------------------------------------------------------------
    // Make an authenticated POST request to HMRC
    // ------------------------------------------------------------------
    public function post(string $endpoint, array $payload, bool $authenticated = true): array
    {
        $headers = ['Content-Type: application/x-www-form-urlencoded'];

        if ($authenticated) {
            $accessToken = $this->getAccessToken();
            $headers[]   = 'Authorization: Bearer ' . $accessToken;
            $headers[]   = 'Accept: application/vnd.hmrc.1.0+json';
            $headers[]   = 'Content-Type: application/json';
            $body        = json_encode($payload);
        } else {
            $body = http_build_query($payload);
        }

        $ch = curl_init(HMRC_BASE_URL . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    // ------------------------------------------------------------------
    // Clear tokens for this user (disconnect)
    // ------------------------------------------------------------------
    public function disconnect(): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM hmrc_tokens WHERE user_id = ?"
        );
        $stmt->execute([$this->user_id]);
    }

    // ------------------------------------------------------------------
    // Get stored tokens from DB
    // ------------------------------------------------------------------
    public function getStoredTokens(): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM hmrc_tokens WHERE user_id = ?"
        );
        $stmt->execute([$this->user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Save tokens to DB
    // ------------------------------------------------------------------
    private function saveTokens(array $response): void
    {
        $expires_at = date('Y-m-d H:i:s', time() + (int)($response['expires_in'] ?? 14400));

        $stmt = $this->pdo->prepare("
            INSERT INTO hmrc_tokens (user_id, access_token, refresh_token, token_expires, updated_at)
            VALUES (:user_id, :access_token, :refresh_token, :token_expires, NOW())
            ON DUPLICATE KEY UPDATE
                access_token  = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                token_expires = VALUES(token_expires),
                updated_at    = NOW()
        ");

        $stmt->execute([
            'user_id'       => $this->user_id,
            'access_token'  => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'token_expires' => $expires_at,
        ]);
    }
}