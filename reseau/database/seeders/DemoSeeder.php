<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\AccountController;
use App\Models\Account;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Jeu de données fictif pour découvrir l'application : un compte « démo » avec des contacts, 70 jours d'habitudes,
 * des repas planifiés, des pesées et un objectif. Tout est relatif à aujourd'hui, donc l'écran paraît toujours vivant.
 *
 * Les noms, numéros et adresses e-mail sont inventés. Relancer le seeder remplace les données du compte démo
 * (et uniquement les siennes).
 */
class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@reseau.test';

    public const PASSWORD = 'demo-password-123';

    private const TZ = 'Africa/Kinshasa';

    /** Lieux : [latitude, longitude]. */
    private const PLACES = [
        'Kinshasa, Gombe' => [-4.3025, 15.3136],
        'Kinshasa, Limete' => [-4.3667, 15.3333],
        'Kinshasa, Ngaliema' => [-4.3500, 15.2500],
        'Kinshasa, Lemba' => [-4.4000, 15.3000],
        'Kinshasa, Kintambo' => [-4.3167, 15.2667],
        'Kinshasa, Bandalungwa' => [-4.3333, 15.2833],
        'Matadi' => [-5.8167, 13.4500],
        'Lubumbashi' => [-11.6609, 27.4794],
        'Goma' => [-1.6792, 29.2228],
        'Brazzaville' => [-4.2634, 15.2429],
        'Paris' => [48.8566, 2.3522],
        'Bruxelles' => [50.8503, 4.3517],
        'Nairobi' => [-1.2921, 36.8219],
        'Johannesburg' => [-26.2041, 28.0473],
    ];

    private int $state = 2026;

    public function run(): void
    {
        $this->state = 2026; // mêmes données à chaque exécution

        User::where('email', self::EMAIL)->delete(); // supprime aussi ses données (suppression en cascade)
        $user = User::create(['name' => 'Démo', 'email' => self::EMAIL, 'password' => self::PASSWORD, 'timezone' => self::TZ]);
        $today = Carbon::now(self::TZ)->startOfDay();

        $this->contacts($user, $today);
        $this->habits($user, $today);
        $this->meals($user, $today);
        $this->measurements($user, $today);
        $this->finance($user, $today);
    }

    /**
     * Générateur pseudo-aléatoire PROPRE à la démo (mulberry32), de 0 inclus à 1 exclu. Le `mt_rand` global n'est pas
     * utilisable ici : d'autres morceaux de code (ramasse-miettes, bibliothèques) peuvent le consommer en passant, ce
     * qui rendait les données différentes d'une exécution à l'autre.
     */
    private function next(): float
    {
        $this->state = ($this->state + 0x6D2B79F5) & 0xFFFFFFFF;
        $t = $this->mul32($this->state ^ ($this->state >> 15), $this->state | 1);
        $t ^= ($t + $this->mul32($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF;

        return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296;
    }

    /** Produit de deux entiers de 32 bits modulo 2^32, sans jamais dépasser les entiers de PHP. */
    private function mul32(int $a, int $b): int
    {
        return ((($a & 0xFFFF) * $b) + (((($a >> 16) * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }

    private function chance(float $p): bool
    {
        return $this->next() < $p;
    }

    private function between(float $min, float $max): float
    {
        return $min + $this->next() * ($max - $min);
    }

    private function pick(array $items): mixed
    {
        return $items[(int) floor($this->next() * count($items))];
    }

    private function contacts(User $user, Carbon $today): void
    {
        // [nom, rôle, domaines, lieu, jours depuis le dernier contact (null = jamais), ancienneté de la fiche (jours), comment, anecdote, service]
        $rows = [
            ['Maître Patience Kalala', 'Avocate d\'affaires', ['juridique', 'finance'], 'Kinshasa, Gombe', 12, 400, 'Présentée par un ancien collègue.', 'En 2021, elle a débloqué un contrat bloqué depuis huit mois en trois coups de fil.', 'owe'],
            ['Dr Joseph Mwamba', 'Médecin généraliste', ['santé'], 'Kinshasa, Limete', 25, 900, 'Mon médecin depuis dix ans.', 'Il m\'a obtenu un rendez-vous chez un spécialiste en 24 heures, alors que l\'attente était de trois mois.', null],
            ['Dr Claire Ilunga', 'Cardiologue', ['santé'], 'Kinshasa, Gombe', 140, 300, 'Rencontrée à une conférence médicale.', null, null],
            ['Grâce Nsimba', 'Directrice d\'agence bancaire', ['finance'], 'Kinshasa, Gombe', 40, 520, 'Voisine de quartier.', null, null],
            ['Ir. Samuel Kabongo', 'Ingénieur en énergie', ['énergie'], 'Lubumbashi', 200, 600, 'Camarade d\'université.', null, null],
            ['Jean-Paul Mukendi', 'Courtier immobilier', ['immobilier'], 'Kinshasa, Ngaliema', 18, 350, 'Recommandé par un cousin.', 'Il connaît chaque rue de Ngaliema et leur vrai prix, avant même qu\'elles soient annoncées.', 'owed'],
            ['Esther Banza', 'Notaire', ['juridique', 'immobilier'], 'Kinshasa, Gombe', 65, 280, 'Rencontrée lors d\'une signature chez le notaire.', null, null],
            ['Alain Tshimanga', 'Chef de la sécurité d\'un hôtel', ['sécurité'], 'Kinshasa, Gombe', 95, 450, 'Ancien voisin.', null, null],
            ['Marie-Louise Kasongo', 'Cheffe de service à l\'administration', ['administration'], 'Kinshasa, Gombe', 33, 700, 'Amie de la famille.', null, null],
            ['Didier Lukusa', 'Transitaire en douane', ['transport', 'commerce'], 'Matadi', 50, 380, 'Contact d\'un client.', 'Un container bloqué au port : il l\'a sorti en deux jours, là où tout le monde parlait de trois semaines.', 'owed'],
            ['Nadine Mbala', 'Journaliste', ['médias'], 'Kinshasa, Limete', 22, 240, 'Rencontrée à un salon.', null, null],
            ['Prof. Théophile Ngoy', 'Professeur d\'université', ['éducation'], 'Kinshasa, Kintambo', null, 90, 'Ancien professeur.', null, null],
            ['Sandra Kimbembe', 'Cheffe de projet informatique', ['informatique'], 'Brazzaville', 75, 330, 'Collègue d\'un ancien projet.', null, null],
            ['Olivier Makiese', 'Plombier', ['artisanat'], 'Kinshasa, Lemba', 10, 800, 'Recommandé par un voisin. Disponible le week-end.', null, null],
            ['Francine Mapendo', 'Restauratrice', ['restauration'], 'Kinshasa, Bandalungwa', 15, 260, 'Rencontrée en dînant dans son restaurant.', 'Elle a nourri toute une équipe de 40 personnes en une nuit, quand le traiteur a fait défaut.', null],
            ['Hervé Luyeye', 'Agent de voyage', ['voyage'], 'Kinshasa, Gombe', 88, 410, 'Ami d\'un ami.', null, null],
            ['Béatrice Mutombo', 'Pharmacienne', ['santé'], 'Kinshasa, Bandalungwa', 30, 220, 'Pharmacie du quartier.', null, null],
            ['Pascal Ngandu', 'Grossiste', ['commerce'], 'Kinshasa, Limete', 45, 500, 'Fournisseur d\'un ami.', null, null],
            ['Amandine Lefèvre', 'Consultante en ressources humaines', ['ressources humaines'], 'Paris', 60, 310, 'Rencontrée en formation.', null, null],
            ['Thomas Dubois', 'Avocat fiscaliste', ['juridique', 'finance'], 'Bruxelles', 120, 640, 'Collègue d\'un ancien employeur.', null, null],
            ['Kevin Otieno', 'Développeur', ['informatique'], 'Nairobi', 35, 150, 'Rencontré à un hackathon.', null, null],
            ['Lindiwe Dlamini', 'Experte en logistique', ['transport'], 'Johannesburg', 150, 420, 'Rencontrée à un forum.', null, null],
            ['Papy Kabasele', 'Chauffeur de confiance', ['transport'], 'Kinshasa, Gombe', 5, 1000, 'Recommandé par un oncle.', 'Il m\'a conduit sous un orage en 2022 sans jamais perdre son calme, ni m\'avoir fait manquer mon vol.', 'owe'],
            ['Odette Tshiala', 'Infirmière', ['santé'], 'Goma', null, 20, 'Rencontrée en voyage.', null, null],
            ['Dr Michel Lemaire', 'Dentiste', ['santé'], 'Paris', 180, 560, 'Dentiste d\'un ami.', null, null],
            ['Chantal Bosco', 'Organisatrice d\'événements', ['commerce', 'médias'], 'Kinshasa, Gombe', 20, 200, 'Rencontrée à un mariage.', null, null],
            ['Rodrigue Mpiana', 'Électricien', ['artisanat', 'énergie'], 'Kinshasa, Limete', 28, 330, 'Recommandé par le plombier.', null, null],
            ['Joëlle Kapinga', 'Comptable', ['finance'], 'Kinshasa, Limete', 14, 480, 'Ancienne collègue.', null, null],
            ['Freddy Mulumba', 'Boulanger', ['restauration'], 'Kinshasa, Lemba', 3, 120, 'La boulangerie en bas de la rue.', 'Son pain du matin est la raison pour laquelle je ne rate jamais le petit-déjeuner.', null],
            ['Anne-Sophie Martin', 'Chargée de mission en ONG', ['administration', 'éducation'], 'Paris', 85, 370, 'Conférence sur l\'éducation.', null, null],
            ['Serge Kitenge', 'Coach sportif', ['sport'], 'Kinshasa, Ngaliema', 8, 60, 'Rencontré à la salle de sport.', null, null],
            ['Yvette Muteba', 'Couturière', ['artisanat'], 'Kinshasa, Lemba', 55, 270, 'Recommandée par une cousine.', null, null],
        ];

        foreach ($rows as $i => [$name, $role, $domains, $place, $lastDaysAgo, $age, $how, $anecdote, $favor]) {
            [$lat, $lng] = self::PLACES[$place];
            $user->contacts()->create([
                'name' => $name,
                'role' => $role,
                'domains' => $domains,
                'place' => $place,
                'lat' => round($lat + $this->between(-0.012, 0.012), 5),
                'lng' => round($lng + $this->between(-0.012, 0.012), 5),
                'phone' => str_starts_with($place, 'Paris') || $place === 'Bruxelles'
                    ? sprintf('+33 6 55 50 %02d %02d', 10 + $i, 20 + $i)
                    : sprintf('+243 81 555 01%02d', $i),
                'email' => Str::slug($name, '.').'@example.org',
                'how' => $how,
                'anecdote' => $anecdote,
                'notes' => null,
                'favor' => $favor,
                'last_contact' => $lastDaysAgo === null ? null : $today->copy()->subDays($lastDaysAgo)->toDateString(),
            ])->forceFill(['created_at' => $today->copy()->subDays($age), 'updated_at' => $today->copy()->subDays($age)])->saveQuietly();
        }
    }

    private function habits(User $user, Carbon $today): void
    {
        $start = $today->copy()->subDays(70);
        $habits = [];
        foreach (['prise-poids-saine', 'soins-du-corps'] as $programKey) {
            foreach (config("programs.$programKey.habits") as $h) {
                $habits[] = $user->habits()->create([
                    'program_key' => $programKey,
                    'name' => $h['name'],
                    'category' => $h['category'],
                    'days' => $h['days'],
                    'time' => $h['time'] ?? null,
                    'reminder' => (bool) ($h['reminder'] ?? false) && ! empty($h['time']),
                    'notes' => $h['notes'] ?? null,
                    'start_date' => $start->toDateString(),
                ]);
            }
        }

        $logs = [];
        $now = now();
        foreach ($habits as $habit) {
            // Quelques habitudes sont devenues des réflexes : séries en cours de plus d'une semaine.
            $streaky = Str::contains($habit->name, ['Boire', 'Petit-déjeuner', 'Brossage des dents (matin)']);

            for ($d = $start->copy(); $d->lte($today); $d->addDay()) {
                if (! $habit->isDueOn($d)) {
                    continue;
                }
                $age = $start->diffInDays($d);
                if ($d->eq($today)) {
                    $done = ($habit->time ?? '23:59') < '12:00' && $this->chance(0.6); // aujourd'hui : la matinée seulement
                } elseif ($streaky && $d->gte($today->copy()->subDays(9))) {
                    $done = true;
                } else {
                    // La régularité progresse au fil des semaines (de 55 % à 90 %), un peu moins le week-end.
                    $p = 0.55 + 0.35 * ($age / 70) - ($d->isWeekend() ? 0.1 : 0) + ($habit->category === 'soins' ? 0.07 : 0);
                    $done = $this->chance(min($p, 0.97));
                }
                if ($done) {
                    $logs[] = ['habit_id' => $habit->id, 'date' => $d->toDateString(), 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        foreach (array_chunk($logs, 200) as $chunk) {
            HabitLog::insert($chunk);
        }
    }

    private function meals(User $user, Carbon $today): void
    {
        $menu = [
            'breakfast' => ['Bouillie de maïs et fruits', 'Pain, œufs et avocat', 'Yaourt, flocons d\'avoine et banane', 'Omelette et pain complet', 'Pain et beurre de cacahuète, lait'],
            'lunch' => ['Riz, poisson et légumes', 'Poulet, plantain et salade', 'Pondu et poisson', 'Haricots, riz et légumes verts', 'Fufu et feuilles de manioc', 'Pâtes, viande hachée et salade'],
            'snack' => ['Banane et arachides', 'Yaourt et fruits secs', 'Fruit de saison', 'Pain et beurre de cacahuète'],
            'dinner' => ['Soupe de légumes et pain', 'Poisson grillé et légumes', 'Riz et lentilles', 'Omelette et salade', 'Poulet et patate douce'],
        ];

        $monday = $today->copy()->startOfWeek();
        for ($d = $monday->copy()->subWeek(); $d->lte($monday->copy()->addDays(13)); $d->addDay()) {
            foreach (['breakfast', 'lunch', 'snack', 'dinner'] as $slot) {
                if ($slot === 'snack' && ! $this->chance(0.7)) {
                    continue;
                }
                $past = $d->lt($today);
                $todayDone = $d->eq($today) && in_array($slot, ['breakfast', 'lunch'], true);
                $done = ($past && $this->chance(0.9)) || $todayDone;
                $user->meals()->create([
                    'date' => $d->toDateString(),
                    'slot' => $slot,
                    'description' => $this->pick($menu[$slot]),
                    'done_at' => $done ? $d->copy()->setTime(['breakfast' => 8, 'lunch' => 13, 'snack' => 16, 'dinner' => 20][$slot], 10) : null,
                ]);
            }
        }
    }

    private function measurements(User $user, Carbon $today): void
    {
        $notes = [
            12 => 'Appétit revenu, les collations aident.',
            27 => 'Fatigué : semaine chargée.',
            41 => 'Nouvelle routine du matin, je la garde.',
            55 => 'Bonne énergie après la marche quotidienne.',
        ];

        // Récit : reprise de poids de 61,2 kg à environ 65,4 kg en dix semaines, avec le bruit normal d'une balance.
        for ($t = 70; $t >= 0; $t--) {
            if ($t % 3 !== 0 && $t > 2 && ! $this->chance(0.12)) {
                continue;
            }
            $progress = (70 - $t) / 70;
            $eased = $progress * 0.85 + 0.15 * sin($progress * M_PI / 2);
            $clamp = fn (float $v) => (int) max(1, min(5, round($v)));

            $user->measurements()->create([
                'date' => $today->copy()->subDays($t)->toDateString(),
                'weight_kg' => round(61.2 + $eased * 4.2 + $this->between(-0.35, 0.35), 1),
                'waist_cm' => round(73.0 + $eased * 2.5 + $this->between(-0.5, 0.5), 1),
                'sleep_hours' => round($this->between(6.0, 8.5) * 2) / 2,
                'energy' => $clamp(2.6 + 1.4 * $progress + $this->between(-0.9, 0.9)),
                'mood' => $clamp(3.0 + 1.0 * $progress + $this->between(-1.0, 1.0)),
                'notes' => $notes[$t] ?? null,
            ]);
        }

        $user->healthGoal()->create(['goal' => 'gain', 'target_weight_kg' => 68.0, 'target_date' => $today->copy()->addDays(75)->toDateString()]);
    }

    /** Crée un compte dont l'historique se termine exactement sur le solde voulu. */
    private function account(User $user, Carbon $today, array $attrs, float $target, array $movements): Account
    {
        $account = $user->accounts()->create($attrs + ['balance' => 0]);
        $sum = array_sum(array_column($movements, 1));
        $first = $today->copy()->subDays(70);

        AccountController::move($account, round($target - $sum, 2), $first->toDateString(), 'Solde initial');
        foreach ($movements as [$daysAgo, $amount, $note]) {
            AccountController::move($account, $amount, $today->copy()->subDays($daysAgo)->toDateString(), $note);
        }

        return $account->fresh();
    }

    private function finance(User $user, Carbon $today): void
    {
        $user->financeSetting()->create(['base_currency' => 'USD', 'rates' => ['CDF' => 2800.0, 'EUR' => 0.92]]);

        $courant = $this->account($user, $today, ['name' => 'Compte courant Rawbank', 'type' => 'bank', 'currency' => 'USD', 'institution' => 'Rawbank', 'location' => 'Agence de Gombe', 'source' => 'Salaire et honoraires'], 3240.0, [
            [62, 1800, 'Salaire'], [58, -420, 'Loyer'], [49, -135.5, 'Courses du mois'], [33, 1800, 'Salaire'], [28, -420, 'Loyer'],
            [20, -210, 'Facture électricité et internet'], [5, 1800, 'Salaire'], [2, -380, 'Scolarité'],
        ]);
        $caisse = $this->account($user, $today, ['name' => 'Caisse à la maison', 'type' => 'cash', 'currency' => 'USD', 'location' => 'Coffre-fort à la maison', 'source' => 'Ventes du commerce'], 650.0, [
            [40, 300, 'Ventes de la semaine'], [26, -120, 'Fournisseur'], [11, 250, 'Ventes de la semaine'],
        ]);
        $this->account($user, $today, ['name' => 'M-Pesa', 'type' => 'mobile', 'currency' => 'CDF', 'institution' => 'Vodacom M-Pesa', 'location' => 'Téléphone', 'source' => 'Petits paiements clients'], 1450000.0, [
            [30, 600000, 'Paiements clients'], [18, -250000, 'Crédit de communication et transport'], [4, 300000, 'Paiements clients'],
        ]);
        $this->account($user, $today, ['name' => 'Épargne projet terrain', 'type' => 'savings', 'currency' => 'USD', 'institution' => 'Equity BCDC', 'location' => 'Agence de Limete', 'source' => 'Économies mensuelles'], 4200.0, [
            [60, 500, 'Versement'], [30, 500, 'Versement'], [2, 500, 'Versement'],
        ]);
        $this->account($user, $today, ['name' => 'Compte en France', 'type' => 'bank', 'currency' => 'EUR', 'institution' => 'Crédit Mutuel', 'location' => 'Paris', 'source' => 'Mission passée'], 780.0, []);
        $this->account($user, $today, ['name' => 'Tontine de la famille', 'type' => 'tontine', 'currency' => 'CDF', 'location' => 'Chez la trésorière', 'source' => 'Cotisation mensuelle', 'notes' => 'Je reçois le pot en décembre.'], 600000.0, [
            [45, 100000, 'Cotisation'], [15, 100000, 'Cotisation'],
        ]);

        $contact = fn (string $name) => $user->contacts()->where('name', $name)->first();
        $debt = function (array $a, array $payments = []) use ($user, $today) {
            $d = $user->debts()->create([
                'direction' => $a['direction'], 'counterparty' => $a['who'], 'contact_id' => $a['contact']?->id, 'amount' => $a['amount'], 'currency' => $a['currency'] ?? 'USD',
                'reason' => $a['reason'], 'opened_on' => $today->copy()->subDays($a['opened'])->toDateString(),
                'due_on' => isset($a['due']) ? $today->copy()->addDays($a['due'])->toDateString() : null,
            ]);
            foreach ($payments as [$daysAgo, $amount, $account]) {
                $movement = $account ? AccountController::move($account, $d->direction === 'owed_to_me' ? $amount : -$amount, $today->copy()->subDays($daysAgo)->toDateString(), 'Règlement '.$a['who']) : null;
                $d->payments()->create(['date' => $today->copy()->subDays($daysAgo)->toDateString(), 'amount' => $amount, 'account_id' => $account?->id, 'movement_id' => $movement?->id]);
            }
            $d->update(['settled_at' => $d->remaining() <= 0.004 ? now() : null]);
        };

        // On me doit
        $debt(['direction' => 'owed_to_me', 'who' => 'Didier Lukusa', 'contact' => $contact('Didier Lukusa'), 'amount' => 1500, 'reason' => 'Avance sur une marchandise', 'opened' => 75, 'due' => -12], [[30, 500, $caisse]]);
        $debt(['direction' => 'owed_to_me', 'who' => 'Pascal Ngandu', 'contact' => $contact('Pascal Ngandu'), 'amount' => 800, 'reason' => 'Prêt pour un stock', 'opened' => 20, 'due' => 9]);
        $debt(['direction' => 'owed_to_me', 'who' => 'Esther Banza', 'contact' => $contact('Esther Banza'), 'amount' => 900000, 'currency' => 'CDF', 'reason' => 'Frais avancés pour un dossier', 'opened' => 12, 'due' => 25]);
        $debt(['direction' => 'owed_to_me', 'who' => 'Jean-Paul Mukendi', 'contact' => $contact('Jean-Paul Mukendi'), 'amount' => 1200, 'reason' => 'Prêt, sans échéance fixée', 'opened' => 140]);
        $debt(['direction' => 'owed_to_me', 'who' => 'Nadine Mbala', 'contact' => $contact('Nadine Mbala'), 'amount' => 200, 'reason' => 'Dépannage', 'opened' => 90, 'due' => -50], [[60, 120, $courant], [35, 80, null]]);
        // Je dois
        $debt(['direction' => 'i_owe', 'who' => 'Grâce Nsimba (banque)', 'contact' => $contact('Grâce Nsimba'), 'amount' => 2000, 'reason' => 'Crédit de trésorerie', 'opened' => 100, 'due' => 20], [[40, 500, $courant]]);
        $debt(['direction' => 'i_owe', 'who' => 'Propriétaire', 'contact' => null, 'amount' => 900, 'reason' => 'Arriérés de loyer', 'opened' => 45, 'due' => 3]);
        $debt(['direction' => 'i_owe', 'who' => 'Thomas Dubois', 'contact' => $contact('Thomas Dubois'), 'amount' => 300, 'currency' => 'EUR', 'reason' => 'Honoraires', 'opened' => 60, 'due' => -4]);

        $asset = fn (string $name, string $category, ?float $value, string $status = 'owned', array $more = []) => $user->assets()->create(
            ['name' => $name, 'category' => $category, 'status' => $status, 'value' => $value, 'currency' => 'USD'] + $more
        );
        $asset('Maison à Ngaliema', 'residence', 85000, 'owned', ['location' => 'Kinshasa, Ngaliema', 'acquired_on' => '2019-06-15', 'purchase_price' => 62000]);
        $asset('Toyota Hilux', 'vehicule', 18500, 'owned', ['location' => 'Kinshasa', 'acquired_on' => '2022-03-01', 'purchase_price' => 24000]);
        $asset('Terrain à Mont-Ngafula', 'terrain', 22000, 'owned', ['location' => 'Kinshasa, Mont-Ngafula', 'acquired_on' => '2021-09-10', 'purchase_price' => 14000]);
        $asset('Ordinateur portable', 'electronique', 1200, 'owned', ['acquired_on' => '2024-01-20', 'purchase_price' => 1500]);
        $asset('Bijoux en or', 'or', 2400, 'owned', ['location' => 'Coffre-fort à la maison']);
        $asset('Stock de marchandises', 'commerce', 6500, 'owned', ['location' => 'Boutique de Limete']);
        $asset('Moto de livraison', 'vehicule', 1800, 'owned', ['location' => 'Boutique de Limete', 'acquired_on' => '2023-05-12', 'purchase_price' => 2200]);
        // Projets d'acquisition : les types « equipement », « mobilier », « crypto », « assurance » restent volontairement vides.
        $asset('Bien locatif à Limete', 'locatif', 45000, 'wanted', ['location' => 'Kinshasa, Limete']);
        $asset('Plantation de cacao', 'agricole', 15000, 'wanted', ['notes' => 'À étudier avec un agronome.']);
        $asset('Placement en parts de société', 'placement', 5000, 'wanted');
    }
}
