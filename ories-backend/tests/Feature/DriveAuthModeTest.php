<?php

namespace Tests\Feature;

use App\Services\GoogleDrive;
use Tests\TestCase;

class DriveAuthModeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/ories-drive-'.uniqid();
        mkdir($this->dir);
        config([
            'ories.drive.folder_id' => 'folder-123',
            'ories.drive.credentials' => "{$this->dir}/service.json",
            'ories.drive.oauth_client' => "{$this->dir}/client.json",
            'ories.drive.oauth_token' => "{$this->dir}/token.json",
        ]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*"));
        rmdir($this->dir);

        parent::tearDown();
    }

    public function test_service_account_mode_uses_the_service_account_file(): void
    {
        config(['ories.drive.auth' => 'service_account']);
        $drive = new GoogleDrive;
        $this->assertFalse($drive->isConfigured());

        file_put_contents("{$this->dir}/service.json", json_encode(['client_email' => 'backup@project.iam.gserviceaccount.com']));

        $this->assertFalse($drive->usesOAuth());
        $this->assertTrue($drive->isConfigured());
        $this->assertSame('backup@project.iam.gserviceaccount.com', $drive->account());
    }

    public function test_oauth_mode_needs_the_client_file_and_a_connected_account(): void
    {
        config(['ories.drive.auth' => 'oauth']);
        $drive = new GoogleDrive;
        file_put_contents("{$this->dir}/client.json", json_encode(['installed' => ['client_id' => 'x', 'client_secret' => 'y']]));

        $this->assertTrue($drive->usesOAuth());
        $this->assertFalse($drive->isConfigured(), 'Not usable until an account is connected.');
        $this->assertNull($drive->account());

        file_put_contents("{$this->dir}/token.json", json_encode(['refresh_token' => 'r', 'account' => 'ories.backup@evsu.edu.ph']));

        $this->assertTrue($drive->isConfigured());
        $this->assertSame('ories.backup@evsu.edu.ph', $drive->account());
    }

    public function test_the_authorization_code_is_read_from_the_redirected_address(): void
    {
        $this->assertSame('4/0Abc_123', GoogleDrive::extractCode('http://localhost/?code=4/0Abc_123&scope=https://www.googleapis.com/auth/drive'));
        $this->assertSame('4/0Abc_123', GoogleDrive::extractCode('  4/0Abc_123 '));
    }

    public function test_connect_command_explains_when_oauth_is_not_selected(): void
    {
        config(['ories.drive.auth' => 'service_account']);

        $this->artisan('ories:drive-connect')
            ->expectsOutputToContain('GOOGLE_DRIVE_AUTH=oauth')
            ->assertFailed();
    }

    public function test_check_command_reports_a_missing_connection(): void
    {
        config(['ories.drive.auth' => 'oauth']);

        $this->artisan('ories:drive-check')
            ->expectsOutputToContain('Google account (OAuth)')
            ->expectsOutputToContain('ories:drive-connect')
            ->assertFailed();
    }

    public function test_storage_status_shows_the_sign_in_mode_and_account(): void
    {
        config(['ories.drive.auth' => 'oauth']);
        file_put_contents("{$this->dir}/client.json", '{}');
        file_put_contents("{$this->dir}/token.json", json_encode(['refresh_token' => 'r', 'account' => 'ories.backup@evsu.edu.ph']));

        $this->actingAsUser('admin@evsu.edu.ph');
        $this->getJson('/api/drive/status')
            ->assertOk()
            ->assertJsonPath('auth', 'oauth')
            ->assertJsonPath('account', 'ories.backup@evsu.edu.ph')
            ->assertJsonPath('configured', true);
    }
}
