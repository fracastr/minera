<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Illuminate\Support\Facades\Storage;

/** Uploads a file to the Google Drive disk and returns its webViewLink. Throws when Drive is unavailable. */
class DriveUploader
{
    public function upload(string $name, string $contents): string
    {
        Storage::disk('google')->put($name, $contents);

        $client = new Google_Client();
        $client->setClientId(config('filesystems.disks.google.clientId'));
        $client->setClientSecret(config('filesystems.disks.google.clientSecret'));
        $client->refreshToken(config('filesystems.disks.google.refreshToken'));
        $files = (new Google_Service_Drive($client))->files->listFiles([
            'q' => "name='".str_replace("'", "\\'", $name)."'",
            'fields' => 'files(webViewLink)',
        ]);
        if (!isset($files[0])) {
            throw new \RuntimeException("Drive did not list {$name} after the upload");
        }

        return $files[0]->webViewLink;
    }
}
