<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientEvaluationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClientEvaluation extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'cycle_year',
        'cycle_quarter',
        'public_token',
        'status',
        'respondent_name',
        'respondent_email',
        'respondent_phone',
        'expires_at',
        'sent_at',
        'nps_score',
        'service_score',
        'delivery_score',
        'communication_score',
        'comment',
        'answered_name',
        'answered_role',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'cycle_year' => 'integer',
            'cycle_quarter' => 'integer',
            'status' => ClientEvaluationStatus::class,
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'answered_at' => 'datetime',
            'nps_score' => 'integer',
            'service_score' => 'integer',
            'delivery_score' => 'integer',
            'communication_score' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cycleLabel(): string
    {
        return sprintf('%d · %dº trimestre', $this->cycle_year, $this->cycle_quarter);
    }

    public function publicUrl(): string
    {
        return route('client-evaluation.public', $this->public_token);
    }

    public function invitationMessage(): string
    {
        $name = trim((string) $this->respondent_name);
        $greeting = $name !== '' ? "Olá, {$name}!" : 'Olá!';

        return $greeting.' A Talents gostaria da sua avaliação sobre o nosso trabalho. Leva cerca de 2 minutos: '.$this->publicUrl();
    }

    public function whatsappUrl(): ?string
    {
        $digits = $this->whatsappDigits();
        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->invitationMessage());
    }

    public function npsBandLabel(): ?string
    {
        if ($this->nps_score === null) {
            return null;
        }

        return match (true) {
            $this->nps_score >= 9 => 'Promotor',
            $this->nps_score >= 7 => 'Neutro',
            default => 'Detrator',
        };
    }

    public function isPendingOpen(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status === ClientEvaluationStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->greaterThan($at);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company?->name,
            'cycle_label' => $this->cycleLabel(),
            'respondent_name' => $this->respondent_name,
            'respondent_email' => $this->respondent_email,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'nps_score' => $this->nps_score,
            'nps_band' => $this->npsBandLabel(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'answered_at' => $this->answered_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminDetail(): array
    {
        return [
            ...$this->toListArray(),
            'company_id' => $this->company_id,
            'cycle_year' => $this->cycle_year,
            'cycle_quarter' => $this->cycle_quarter,
            'respondent_phone' => $this->respondent_phone,
            'public_url' => $this->publicUrl(),
            'whatsapp_url' => $this->whatsappUrl(),
            'invitation_message' => $this->invitationMessage(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'service_score' => $this->service_score,
            'delivery_score' => $this->delivery_score,
            'communication_score' => $this->communication_score,
            'comment' => $this->comment,
            'answered_name' => $this->answered_name,
            'answered_role' => $this->answered_role,
            'can_email' => $this->isPendingOpen() && filled($this->respondent_email),
            'can_replace' => $this->status !== ClientEvaluationStatus::Answered,
            'can_share' => $this->isPendingOpen(),
        ];
    }

    private function whatsappDigits(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->respondent_phone) ?? '';
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            $digits = '55'.$digits;
        }

        if (strlen($digits) < 12 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }
}
