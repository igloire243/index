<?php

namespace App\Console\Commands;

use App\Models\Habit;
use App\Models\User;
use App\Services\PushSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SendReminders extends Command
{
    protected $signature = 'reseau:send-reminders';

    protected $description = "Envoie les rappels d'habitudes dont l'heure est passée (à lancer chaque minute).";

    public function handle(PushSender $sender): int
    {
        if (! $sender->enabled()) {
            $this->warn('Notifications push désactivées : clés VAPID absentes (php artisan reseau:vapid).');

            return self::SUCCESS;
        }

        $window = (int) config('reseau.reminder_window_minutes', 180);
        $sent = 0;

        User::query()->whereHas('pushSubscriptions')->with('pushSubscriptions')->each(function (User $user) use ($sender, $window, &$sent) {
            $now = $this->localNow($user);
            $today = $now->toDateString();

            $due = $user->habits()
                ->where('reminder', true)->whereNotNull('time')->whereNull('archived_at')
                ->where(fn ($q) => $q->whereNull('last_reminded_on')->orWhere('last_reminded_on', '<', $today))
                ->get()
                ->filter(function (Habit $habit) use ($now, $today, $window) {
                    if (! $habit->isDueOn($now)) {
                        return false;
                    }
                    $dueAt = $now->copy()->setTimeFromTimeString($habit->time);
                    if ($now->lt($dueAt) || $dueAt->diffInMinutes($now) > $window) {
                        return false;
                    }

                    return ! $habit->logs()->whereDate('date', $today)->exists();
                })
                ->values();

            if ($due->isEmpty()) {
                return;
            }

            $payload = [
                'title' => $due->count() === 1 ? 'Rappel santé' : $due->count().' rappels santé',
                'body' => $due->take(3)->pluck('name')->implode(' · ').($due->count() > 3 ? '…' : ''),
                'url' => '/sante',
                'tag' => 'habits-'.$today,
            ];

            $delivered = false;
            foreach ($user->pushSubscriptions as $subscription) {
                $result = $sender->send($subscription, $payload);
                $delivered = $delivered || $result === PushSender::SENT;
                if ($result === PushSender::GONE) {
                    $subscription->delete();
                }
            }

            // Un rappel n'est marqué comme envoyé que s'il est vraiment parti : sinon on réessaie à la minute suivante.
            if ($delivered) {
                Habit::whereIn('id', $due->pluck('id'))->update(['last_reminded_on' => $today]);
                $sent += $due->count();
            }
        });

        $this->info("$sent rappel(s) envoyé(s).");

        return self::SUCCESS;
    }

    private function localNow(User $user): Carbon
    {
        try {
            return Carbon::now($user->timezone);
        } catch (Throwable) {
            return Carbon::now('UTC');
        }
    }
}
