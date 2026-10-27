<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

/**
 * Thin wrapper around the Google Drive API used for MOV backups and for importing
 * files that faculty already keep in the shared drop-off folder.
 *
 * Drive can be reached two ways (GOOGLE_DRIVE_AUTH): a service account, which can only store
 * files in a Shared Drive, or a regular Google account connected once through OAuth, whose
 * own My Drive receives the backups.
 */
class GoogleDrive
{
    // Loopback redirect accepted by Google for "Desktop app" OAuth clients without registration
    public const OAUTH_REDIRECT = 'http://localhost';

    private ?Drive $service = null;

    public function usesOAuth(): bool
    {
        return config('ories.drive.auth') === 'oauth';
    }

    public function isConfigured(): bool
    {
        if (! config('ories.drive.folder_id')) {
            return false;
        }

        return $this->usesOAuth()
            ? $this->isFile(config('ories.drive.oauth_client')) && $this->isFile(config('ories.drive.oauth_token'))
            : $this->isFile(config('ories.drive.credentials'));
    }

    public function backupEnabled(): bool
    {
        return config('ories.drive.backup_enabled') && $this->isConfigured();
    }

    /**
     * The account files are uploaded as: the connected Google account, or the service account's e-mail.
     */
    public function account(): ?string
    {
        $file = $this->usesOAuth() ? config('ories.drive.oauth_token') : config('ories.drive.credentials');
        if (! $this->isFile($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return $this->usesOAuth() ? ($data['account'] ?? null) : ($data['client_email'] ?? null);
    }

    /**
     * Uploads a file's contents and returns the new Drive file ID.
     */
    public function upload(string $contents, string $name, ?string $mimeType): string
    {
        $file = new DriveFile([
            'name' => $name,
            'parents' => [config('ories.drive.folder_id')],
        ]);

        $created = $this->service()->files->create($file, [
            'data' => $contents,
            'mimeType' => $mimeType ?: 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        return $created->getId();
    }

    /**
     * Confirms that the backup folder can be read and written by creating and deleting a small
     * test file (reading alone succeeds even where uploads are refused). Returns the folder name.
     */
    public function testBackupFolder(): string
    {
        $folderId = config('ories.drive.folder_id');
        $name = (string) $this->service()->files->get($folderId, ['fields' => 'name', 'supportsAllDrives' => true])->getName();

        $probe = $this->service()->files->create(new DriveFile(['name' => 'ories-is-connection-test.txt', 'parents' => [$folderId]]), [
            'data' => 'ORIES-IS connection test. Safe to delete.',
            'mimeType' => 'text/plain',
            'uploadType' => 'multipart',
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);
        $this->service()->files->delete($probe->getId(), ['supportsAllDrives' => true]);

        return $name;
    }

    /**
     * @return array<int, array{id:string, name:string, mime_type:?string, size:?int, url:?string, created_at:?string}>
     */
    public function listImportFolder(int $limit = 50): array
    {
        $folderId = config('ories.drive.import_folder_id');

        $results = $this->service()->files->listFiles([
            'q' => "'{$folderId}' in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'",
            'fields' => 'files(id, name, mimeType, size, webViewLink, createdTime)',
            'orderBy' => 'createdTime desc',
            'pageSize' => $limit,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return array_map(fn (DriveFile $file) => [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ? (int) $file->getSize() : null,
            'url' => $file->getWebViewLink(),
            'created_at' => $file->getCreatedTime() ? date('Y-m-d H:i:s', strtotime($file->getCreatedTime())) : null,
        ], $results->getFiles());
    }

    /**
     * @return array{name:string, mime_type:?string, contents:string}
     */
    public function download(string $fileId): array
    {
        $meta = $this->service()->files->get($fileId, ['fields' => 'name, mimeType', 'supportsAllDrives' => true]);

        if (str_starts_with((string) $meta->getMimeType(), 'application/vnd.google-apps.')) {
            // Native Google Docs/Sheets have no binary content; export them as PDF
            $response = $this->service()->files->export($fileId, 'application/pdf', ['alt' => 'media']);

            return ['name' => $meta->getName().'.pdf', 'mime_type' => 'application/pdf', 'contents' => (string) $response->getBody()];
        }

        $response = $this->service()->files->get($fileId, ['alt' => 'media', 'supportsAllDrives' => true]);

        return ['name' => $meta->getName(), 'mime_type' => $meta->getMimeType(), 'contents' => (string) $response->getBody()];
    }

    /**
     * Google sign-in page for the one-time OAuth connection.
     */
    public function authorizationUrl(): string
    {
        return $this->oauthClient()->createAuthUrl();
    }

    /**
     * Exchanges the code from the redirect (or the whole redirected URL) for a long-lived refresh token
     * and stores it. Returns the connected account's e-mail.
     */
    public function connect(string $codeOrRedirectUrl): string
    {
        $client = $this->oauthClient();
        $token = $client->fetchAccessTokenWithAuthCode(self::extractCode($codeOrRedirectUrl));

        if (isset($token['error'])) {
            throw new RuntimeException('Google rejected the code: '.($token['error_description'] ?? $token['error']));
        }
        if (empty($token['refresh_token'])) {
            throw new RuntimeException('Google did not return a refresh token. Remove ORIES-IS from the account\'s third-party access (myaccount.google.com/permissions) and connect again.');
        }

        $client->setAccessToken($token);
        $account = (string) (new Drive($client))->about->get(['fields' => 'user'])->getUser()->getEmailAddress();

        $this->saveToken([...$token, 'account' => $account]);
        $this->service = null;

        return $account;
    }

    /**
     * Accepts either the bare authorization code or the full redirected URL (http://localhost/?code=...).
     */
    public static function extractCode(string $input): string
    {
        $input = trim($input);
        $query = parse_url($input, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            if (! empty($params['code'])) {
                return $params['code'];
            }
        }

        return $input;
    }

    private function service(): Drive
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException($this->usesOAuth()
                ? 'Google Drive is not connected. Run `php artisan ories:drive-connect`.'
                : 'Google Drive is not configured.');
        }

        if ($this->service === null) {
            $this->service = new Drive($this->usesOAuth() ? $this->authorizedOAuthClient() : $this->serviceAccountClient());
        }

        return $this->service;
    }

    private function serviceAccountClient(): Client
    {
        $client = new Client;
        $client->setAuthConfig(config('ories.drive.credentials'));
        $client->addScope(Drive::DRIVE);

        return $client;
    }

    private function oauthClient(): Client
    {
        $path = config('ories.drive.oauth_client');
        if (! $this->isFile($path)) {
            throw new RuntimeException("OAuth client file not found at {$path}. Download it from Google Cloud Console (OAuth client ID, type \"Desktop app\").");
        }

        $client = new Client;
        $client->setAuthConfig($path);
        $client->addScope(Drive::DRIVE);
        $client->setRedirectUri(self::OAUTH_REDIRECT);
        $client->setAccessType('offline');
        // Always show the consent screen so Google issues a refresh token
        $client->setPrompt('consent');

        return $client;
    }

    /**
     * OAuth client with the stored token, refreshing the short-lived access token when it has expired.
     */
    private function authorizedOAuthClient(): Client
    {
        $client = $this->oauthClient();
        $stored = json_decode((string) file_get_contents(config('ories.drive.oauth_token')), true) ?: [];
        $client->setAccessToken($stored);

        if ($client->isAccessTokenExpired()) {
            $fresh = $client->fetchAccessTokenWithRefreshToken($stored['refresh_token'] ?? null);
            if (isset($fresh['error'])) {
                throw new RuntimeException('The Google account connection has expired or was revoked. Run `php artisan ories:drive-connect` again.');
            }
            // Google omits the refresh token on refresh; keep the original one
            $this->saveToken([...$fresh, 'refresh_token' => $stored['refresh_token'], 'account' => $stored['account'] ?? null]);
        }

        return $client;
    }

    private function saveToken(array $token): void
    {
        file_put_contents(config('ories.drive.oauth_token'), json_encode($token, JSON_PRETTY_PRINT));
    }

    private function isFile(?string $path): bool
    {
        return $path !== null && $path !== '' && is_file($path);
    }
}
