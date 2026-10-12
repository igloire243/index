<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dossier extends Model
{
    protected $hidden = ['user_id', 'reminder_sent_for'];

    protected $fillable = [
        'contact_id', 'subject', 'title', 'summary',
        'category', 'document_number', 'issued_by', 'issue_date', 'expiry_date', 'reminder_sent_for',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'encrypted',
            'document_number' => 'encrypted',
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'reminder_sent_for' => 'date:Y-m-d',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(DossierFile::class)->where('status', 'ready')->orderBy('id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
