<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .muted { color: #64748b; }
    h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #4338ca; border-bottom: 1px solid #c7d2fe; padding-bottom: 3px; margin: 18px 0 8px; }
    ul { margin: 0; padding-left: 16px; }
    li { margin-bottom: 4px; }
    .entry { margin-bottom: 10px; }
    .entry-title { font-weight: bold; }
    .entry-meta { color: #64748b; font-size: 10px; }
    .entry-desc { margin-top: 2px; white-space: pre-wrap; }
    .tags span { display: inline-block; background: #eef2ff; color: #4338ca; border-radius: 10px; padding: 2px 8px; margin: 0 4px 4px 0; font-size: 10px; }
</style>
</head>
<body>
    <h1>{{ $user->name }}</h1>
    <p class="muted">{{ $user->email }}</p>

    @if ($languages->isNotEmpty())
        <h2>Langues</h2>
        <p class="tags">
            @foreach ($languages as $l)
                <span>{{ $l->name }} — {{ ['notions' => 'notions', 'intermediaire' => 'intermédiaire', 'courant' => 'courant', 'natif' => 'natif'][$l->level] ?? $l->level }}</span>
            @endforeach
        </p>
    @endif

    @foreach (['competence' => 'Compétences', 'connaissance' => 'Connaissances', 'talent' => 'Talents'] as $type => $label)
        @php($list = $skills->where('type', $type))
        @if ($list->isNotEmpty())
            <h2>{{ $label }}</h2>
            <p class="tags">
                @foreach ($list as $s)
                    <span>{{ $s->name }}{{ $s->level ? ' ('.$s->level.'/5)' : '' }}</span>
                @endforeach
            </p>
        @endif
    @endforeach

    @if ($experiences->isNotEmpty())
        <h2>Expériences</h2>
        @foreach ($experiences as $e)
            <div class="entry">
                <div class="entry-title">{{ $e->title }}@if ($e->organization) — {{ $e->organization }}@endif</div>
                <div class="entry-meta">
                    @if ($e->start_date){{ $e->start_date->translatedFormat('F Y') }} @endif
                    —
                    @if ($e->current) en cours @elseif ($e->end_date) {{ $e->end_date->translatedFormat('F Y') }} @endif
                    @if ($e->location) · {{ $e->location }} @endif
                </div>
                @if ($e->description)
                    <div class="entry-desc">{{ $e->description }}</div>
                @endif
                @if ($e->achievements->isNotEmpty())
                    <ul>
                        @foreach ($e->achievements as $a)
                            <li>{{ $a->title }}@if ($a->date) ({{ $a->date->translatedFormat('F Y') }})@endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif

    @if ($achievements->isNotEmpty())
        <h2>Réalisations</h2>
        <ul>
            @foreach ($achievements as $a)
                <li><b>{{ $a->title }}</b>@if ($a->date) ({{ $a->date->translatedFormat('F Y') }})@endif @if ($a->description) — {{ $a->description }} @endif</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
