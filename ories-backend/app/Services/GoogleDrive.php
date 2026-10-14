<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

/**
 * Thin wrapper around the Google Drive API used for MOV backups and for importing
 * files that faculty already keep in the shared drop-off folder.
 */
class GoogleDrive
{
    private ?Drive $service = null;

    public function isConfigured(): bool
    {
        $credentials = config('ories.drive.credentials');

        return $credentials && is_file($credentials) && config('ories.drive.folder_id');
    }

    public function backupEnabled(): bool
    {
        return config('ories.drive.backup_enabled') && $this->isConfigured();
    }

    /**
     * Uploads a locally stored file and returns the new Drive file ID.
     */
    public function upload(string $localPath, string $name, ?string $mimeType): string
    {
        $file = new DriveFile([
            'name' => $name,
            'parents' => [config('ories.drive.folder_id')],
        ]);

        $created = $this->service()->files->create($file, [
            'data' => file_get_contents($localPath),
            'mimeType' => $mimeType ?: 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        return $created->getId();
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

    private function service(): Drive
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Drive is not configured.');
        }

        if ($this->service === null) {
            $client = new Client;
            $client->setAuthConfig(config('ories.drive.credentials'));
            $client->addScope(Drive::DRIVE);
            $this->service = new Drive($client);
        }

        return $this->service;
    }
}
