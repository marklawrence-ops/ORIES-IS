<?php

use App\Jobs\BackupAttachmentToDrive;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\DuplicateTitleChecker;
use App\Services\GoogleDrive;
use App\Services\Notifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ories:remind-stale', function (Notifier $notifier) {
    $days = (int) config('ories.stale_after_days');

    // Ongoing records whose last verified change is older than the reminder window
    $projects = Project::query()
        ->verified()
        ->where('stage', 'ongoing')
        ->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays($days)))
        ->whereDoesntHave('updates', fn ($q) => $q->where('status', 'pending'))
        // Records past their end date get the more specific deadline reminder instead
        ->whereNotIn('id', Project::query()->pastEndDate()->select('id'))
        ->with('lead')
        ->get();

    foreach ($projects as $project) {
        $notifier->send(
            $project->lead,
            'Status update reminder',
            'No update has been verified for "'.$project->title.'" in over '.$days.' days. Submit its current status or outcomes.',
            '/projects/'.$project->id,
        );
    }

    $this->info("Sent {$projects->count()} reminder(s).");
})->purpose('Remind leads of ongoing records that have not been updated recently');

Artisan::command('ories:remind-deadlines', function (Notifier $notifier) {
    // Leads who already submitted an update (e.g., the completion report) are not reminded again
    $due = fn ($query) => $query->whereDoesntHave('updates', fn ($q) => $q->where('status', 'pending'))->with('lead')->get();

    $overdue = $due(Project::query()->pastEndDate());
    foreach ($overdue as $project) {
        $notifier->send(
            $project->lead,
            'Record past its end date',
            '"'.$project->title.'" was due to end on '.$project->end_date->format('F j, Y').'. Submit a completion or termination update with its Means of Verification.',
            '/projects/'.$project->id,
        );
    }

    $endingSoon = $due(Project::query()->endingSoon());
    foreach ($endingSoon as $project) {
        $notifier->send(
            $project->lead,
            'Record ending soon',
            '"'.$project->title.'" is scheduled to end on '.$project->end_date->format('F j, Y').'. Prepare its completion report and Means of Verification.',
            '/projects/'.$project->id,
        );
    }

    $this->info("Sent {$overdue->count()} overdue and {$endingSoon->count()} ending-soon reminder(s).");
})->purpose('Remind leads of ongoing records that are past or near their end date');

Artisan::command('ories:remind-verifiers', function (Notifier $notifier) {
    $days = (int) config('ories.verify_within_days');

    // One notice per verifier, counting only the offices they verify
    $verifiers = User::query()->whereIn('role', ['staff', 'director'])->where('status', 'active')->get();
    $reminded = 0;
    foreach ($verifiers as $verifier) {
        $waiting = ProjectUpdate::inQueueOf($verifier)->waitingTooLong()->count();
        if ($waiting === 0) {
            continue;
        }

        $notifier->send(
            $verifier,
            'Submissions waiting for verification',
            $waiting.' submission(s) in your queue have waited more than '.$days.' working days.',
            '/verification',
        );
        $reminded++;
    }

    $this->info("Reminded {$reminded} verifier(s).");
})->purpose('Remind verifiers of submissions waiting longer than the verification window');

Artisan::command('ories:retry-drive-backups', function (GoogleDrive $drive) {
    if (! $drive->backupEnabled()) {
        $this->warn('Google Drive backup is disabled or not configured.');

        return;
    }

    // Pending backups already have a queued job; only re-queue them once they look lost (over a day old)
    $ids = Attachment::query()
        ->where(fn ($q) => $q->whereIn('drive_status', ['failed', 'disabled'])
            ->orWhere(fn ($p) => $p->where('drive_status', 'pending')->where('updated_at', '<', now()->subDay())))
        ->pluck('id');
    Attachment::whereIn('id', $ids)->update(['drive_status' => 'pending']);
    $ids->each(fn ($id) => BackupAttachmentToDrive::dispatch($id));

    $this->info("Queued {$ids->count()} attachment(s) for Google Drive backup.");
})->purpose('Queue Google Drive backups for attachments that are not yet synced');

Artisan::command('ories:drive-connect', function (GoogleDrive $drive) {
    if (! $drive->usesOAuth()) {
        $this->error('Set GOOGLE_DRIVE_AUTH=oauth in .env first; the service account mode needs no sign-in.');

        return 1;
    }

    try {
        $url = $drive->authorizationUrl();
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->line('1. Open this link and sign in with the Google account that should hold the backups:');
    $this->newLine();
    $this->line($url);
    $this->newLine();
    $this->line('2. Allow access. The browser then opens a "localhost" page that does not load; that is expected.');
    $this->line('3. Copy the full address from the browser\'s address bar and paste it here.');

    try {
        $account = $drive->connect((string) $this->ask('Address (or just the code)'));
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->info("Connected as {$account}. Run `php artisan ories:drive-check` to confirm the backup folder, then restart the queue worker.");
})->purpose('Connect a regular Google account for Drive backups (GOOGLE_DRIVE_AUTH=oauth)');

Artisan::command('ories:drive-check', function (GoogleDrive $drive) {
    $this->line('Sign-in mode: '.($drive->usesOAuth() ? 'Google account (OAuth)' : 'service account'));
    $this->line('Account: '.($drive->account() ?? 'none'));
    $this->line('Automatic backup: '.(config('ories.drive.backup_enabled') ? 'on' : 'off'));

    if (! $drive->isConfigured()) {
        $this->error($drive->usesOAuth()
            ? 'Not ready: set GOOGLE_DRIVE_FOLDER_ID and run `php artisan ories:drive-connect`.'
            : 'Not ready: set GOOGLE_DRIVE_FOLDER_ID and add the service account file.');

        return 1;
    }

    try {
        $this->info('Backup folder "'.$drive->testBackupFolder().'" can be read and written. Backups will work.');
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $this->error('The backup folder cannot be used: '.$message);
        if (str_contains($message, 'storageQuotaExceeded') || str_contains($message, 'do not have storage quota')) {
            $this->line('A service account cannot store files in a personal My Drive. Use a Shared Drive folder, or set GOOGLE_DRIVE_AUTH=oauth and run `php artisan ories:drive-connect`.');
        }

        return 1;
    }
})->purpose('Test the Google Drive connection by writing and deleting a test file in the backup folder');

Artisan::command('ories:evaluate-crosscheck {file=database/data/crosscheck_validation.csv}', function (string $file) {
    $rows = array_map('str_getcsv', file(base_path($file), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    array_shift($rows);

    $scored = array_map(fn ($r) => [
        'category' => $r[0],
        'duplicate' => $r[1] === 'duplicate',
        'plain' => DuplicateTitleChecker::jaroWinkler(DuplicateTitleChecker::normalize($r[2]), DuplicateTitleChecker::normalize($r[3])),
        'score' => DuplicateTitleChecker::similarity(DuplicateTitleChecker::normalize($r[2]), DuplicateTitleChecker::normalize($r[3])),
        'pair' => $r[2].' | '.$r[3],
    ], $rows);

    // Confusion matrix of each threshold for plain and token-sorted Jaro-Winkler
    $table = [];
    foreach (['plain', 'score'] as $method) {
        foreach ([0.75, 0.80, 0.85, 0.90, 0.95] as $t) {
            $tp = $fp = $tn = $fn = 0;
            foreach ($scored as $s) {
                $flagged = $s[$method] >= $t;
                $s['duplicate'] ? ($flagged ? $tp++ : $fn++) : ($flagged ? $fp++ : $tn++);
            }
            $precision = $tp + $fp ? $tp / ($tp + $fp) : 0;
            $recall = $tp + $fn ? $tp / ($tp + $fn) : 0;
            $table[] = [
                $method === 'plain' ? 'Jaro-Winkler' : 'Jaro-Winkler + token sort', number_format($t, 2), $tp, $fp, $tn, $fn,
                number_format($precision * 100, 1).'%', number_format($recall * 100, 1).'%',
                number_format(($tp + $tn) / count($scored) * 100, 1).'%',
                number_format($precision + $recall ? 2 * $precision * $recall / ($precision + $recall) : 0, 3),
            ];
        }
    }
    $this->table(['Method', 'Threshold', 'TP', 'FP', 'TN', 'FN', 'Precision', 'Recall', 'Accuracy', 'F1'], $table);

    $threshold = (float) config('ories.duplicate_threshold');
    $byCategory = collect($scored)->groupBy('category')->map(fn ($g, $c) => [
        $c, $g->count(), $g->first()['duplicate'] ? 'duplicate' : 'distinct',
        number_format($g->avg('plain'), 3), number_format($g->avg('score'), 3),
        $g->filter(fn ($s) => $s['score'] >= $threshold)->count(),
    ])->values()->all();
    $this->table(['Category', 'Pairs', 'Label', 'Mean JW', 'Mean JW+sort', 'Flagged at '.$threshold], $byCategory);

    $this->line('Misclassified at threshold '.$threshold.':');
    foreach ($scored as $s) {
        if (($s['score'] >= $threshold) !== $s['duplicate']) {
            $this->line(sprintf('  [%s] %.4f %s', $s['duplicate'] ? 'missed duplicate' : 'false flag', $s['score'], $s['pair']));
        }
    }
})->purpose('Evaluate the Crosschecking Module against a labeled set of title pairs');

Schedule::command('ories:remind-stale')->weeklyOn(1, '8:00');
Schedule::command('ories:remind-deadlines')->weeklyOn(1, '8:00');
Schedule::command('ories:remind-verifiers')->weekdays()->at('8:00');
Schedule::command('ories:retry-drive-backups')->hourly();
