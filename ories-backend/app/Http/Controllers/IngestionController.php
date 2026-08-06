<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Drive;

class IngestionController extends Controller
{
    public function fetchDriveFiles(Request $request)
    {
        try {
            $client = new Client();
            
            // Point to the credentials file stored securely in storage/app
            $client->setAuthConfig(storage_path('app/google-credentials.json'));
            $client->addScope(Drive::DRIVE_READONLY);

            $service = new Drive($client);
            $folderId = env('GOOGLE_DRIVE_FOLDER_ID');

            if (!$folderId) {
                return response()->json(['message' => 'Google Drive Folder ID is not configured.'], 500);
            }

            // Query only files directly inside the specified drop-off folder
            $query = "'" . $folderId . "' in parents and trashed = false";
            $optParams = [
                'q' => $query,
                'fields' => 'files(id, name, webViewLink, createdTime)',
                'orderBy' => 'createdTime desc', // Fetch newest files first
                'pageSize' => 20,
            ];

            $results = $service->files->listFiles($optParams);
            $files = $results->getFiles();

            $formattedFiles = [];
            foreach ($files as $file) {
                $formattedFiles[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'url' => $file->getWebViewLink(), // Link to view/download the file
                    'created_at' => date('Y-m-d H:i:s', strtotime($file->getCreatedTime())),
                ];
            }

            return response()->json(['files' => $formattedFiles], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch files from Google Drive.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}