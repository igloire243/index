<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use App\Models\Habit;
use App\Models\Project;
use App\Models\User;
use App\Services\PushSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendReminders extends Command
{
    protected $signature = 'reseau:send-reminders';

    protected $description = "Envoie les rappels d'habitudes dont l'heure est passée, les échéances de projets et l'expiration prochaine de documents (à lancer chaque minute).";

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

            if ($due->isNotEmpty()) {
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
            }

            $sent += $this->sendProjectReminders($sender, $user, $now);
            $sent += $this->sendDocumentReminders($sender, $user, $now);
        });

        $this->info("$sent rappel(s) envoyé(s).");

        return self::SUCCESS;
    }

    /**
     * Un rappel, une fois, pour chaque projet (actif, avec échéance) qui arrive à échéance aujourd'hui ou demain.
     * `reminder_sent_for` empêche de le renvoyer tant que l'échéance ne change pas (voir ProjectController).
     */
    private function sendProjectReminders(PushSender $sender, User $user, Carbon $now): int
    {
        $today = $now->toDateString();
        $tomorrow = $now->copy()->addDay()->toDateString();

        $due = $user->projects()
            ->whereIn('status', ['idea', 'in_progress'])
            ->whereIn('target_date', [$today, $tomorrow])
            ->where(fn ($q) => $q->whereNull('reminder_sent_for')->orWhereColumn('reminder_sent_for', '!=', 'target_date'))
            ->get();

        if ($due->isEmpty()) {
            return 0;
        }

        $delivered = false;
        foreach ($user->pushSubscriptions as $subscription) {
            foreach ($due as $project) {
                $payload = [
                    'title' => $project->target_date->toDateString() === $today ? 'Échéance aujourd’hui' : 'Échéance demain',
                    'body' => $project->title,
                    'url' => '/coach/projets/'.$project->id,
                    'tag' => 'projet-'.$project->id,
                ];
                $result = $sender->send($subscription, $payload);
                $delivered = $delivered || $result === PushSender::SENT;
                if ($result === PushSender::GONE) {
                    $subscription->delete();

                    break;
                }
            }
        }

        if ($delivered) {
            Project::whereIn('id', $due->pluck('id'))->update(['reminder_sent_for' => DB::raw('target_date')]);
        }

        return $delivered ? $due->count() : 0;
    }

    /**
     * Un rappel, une fois, pour chaque document personnel dont l'échéance (passeport, carte d'identité, permis…)
     * arrive dans les 30 prochains jours. `reminder_sent_for` empêche de le renvoyer tant que l'échéance ne change
     * pas (voir DossierController) — même mécanisme que pour les projets.
     */
    private function sendDocumentReminders(PushSender $sender, User $user, Carbon $now): int
    {
        $today = $now->toDateString();
        $horizon = $now->copy()->addDays(30)->toDateString();

        $due = $user->dossiers()
            ->whereNotNull('category')
            ->whereBetween('expiry_date', [$today, $horizon])
            ->where(fn ($q) => $q->whereNull('reminder_sent_for')->orWhereColumn('reminder_sent_for', '!=', 'expiry_date'))
            ->get();

        if ($due->isEmpty()) {
            return 0;
        }

        $delivered = false;
        foreach ($user->pushSubscriptions as $subscription) {
            foreach ($due as $document) {
                $days = (int) $now->copy()->startOfDay()->diffInDays($document->expiry_date, false);
                $payload = [
                    'title' => $days <= 0 ? 'Document expiré' : ($days === 1 ? 'Expire demain' : "Expire dans $days jours"),
                    'body' => $document->title,
                    'url' => '/coffre/'.$document->id,
                    'tag' => 'document-'.$document->id,
                ];
                $result = $sender->send($subscription, $payload);
                $delivered = $delivered || $result === PushSender::SENT;
                if ($result === PushSender::GONE) {
                    $subscription->delete();

                    break;
                }
            }
        }

        if ($delivered) {
            Dossier::whereIn('id', $due->pluck('id'))->update(['reminder_sent_for' => DB::raw('expiry_date')]);
        }

        return $delivered ? $due->count() : 0;
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
