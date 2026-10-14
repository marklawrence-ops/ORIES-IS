<?php

use App\Jobs\BackupAttachmentToDrive;
use App\Models\Attachment;
use App\Models\Project;
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
Schedule::command('ories:retry-drive-backups')->hourly();
