<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Notifier
{
    /**
     * @param  User|iterable<User>  $users
     */
    public function send(User|iterable $users, string $title, ?string $body = null, ?string $link = null): void
    {
        $recipients = $users instanceof User ? collect([$users]) : Collection::make($users);

        foreach ($recipients->unique('id') as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'link' => $link,
            ]);

            if (config('ories.mail_notifications')) {
                $this->mail($user, $title, $body, $link);
            }
        }
    }

    /**
     * Staff and directors responsible for verifying records of the given office.
     */
    public function verifiersFor(string $office): Collection
    {
        return User::query()
            ->whereIn('role', ['staff', 'director'])
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('office')->orWhere('office', $office))
            ->get();
    }

    private function mail(User $user, string $title, ?string $body, ?string $link): void
    {
        try {
            $text = trim($body."\n\n".($link ? rtrim(config('app.frontend_url'), '/').$link : ''));
            Mail::raw($text, fn ($message) => $message->to($user->email)->subject('[ORIES-IS] '.$title));
        } catch (Throwable $e) {
            // In-app notifications remain the source of truth when mail delivery fails
            Log::warning('Notification mail failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
