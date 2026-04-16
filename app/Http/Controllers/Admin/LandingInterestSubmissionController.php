<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingInterestSubmission;
use Inertia\Inertia;
use Inertia\Response;

class LandingInterestSubmissionController extends Controller
{
    public function index(): Response
    {
        $paginator = LandingInterestSubmission::query()
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (LandingInterestSubmission $s) => self::submissionRow($s));

        return Inertia::render('Admin/LandingInterest/Index', [
            'submissions' => self::scrubPaginatorArray($paginator->toArray()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function submissionRow(LandingInterestSubmission $s): array
    {
        return [
            'id' => $s->id,
            'name' => self::asUtf8String($s->name, ''),
            'email' => self::asUtf8String($s->email, ''),
            'company' => self::asUtf8String($s->company),
            'message' => self::asUtf8String($s->message),
            'mail_sent_at' => $s->mail_sent_at?->toIso8601String(),
            'mail_error' => self::asUtf8String($s->mail_error),
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }

    /**
     * Garante string UTF-8 válida para JSON/Inertia (evita null e bytes inválidos no htmlspecialchars interno).
     */
    private static function asUtf8String(mixed $value, ?string $whenEmpty = null): ?string
    {
        if ($value === null) {
            return $whenEmpty;
        }
        if (! is_string($value)) {
            $value = (string) $value;
        }
        if ($value === '') {
            return $whenEmpty;
        }

        $clean = mb_scrub($value, 'UTF-8');

        return $clean === '' ? $whenEmpty : $clean;
    }

    /**
     * @param  array<string, mixed>  $paginator
     * @return array<string, mixed>
     */
    private static function scrubPaginatorArray(array $paginator): array
    {
        $links = $paginator['links'] ?? [];
        if (! is_array($links)) {
            $paginator['links'] = [];

            return $paginator;
        }

        $paginator['links'] = array_values(array_map(function (mixed $link): array {
            if (! is_array($link)) {
                return ['url' => null, 'label' => '', 'active' => false];
            }

            $label = $link['label'] ?? '';
            if (! is_string($label)) {
                $label = (string) $label;
            }
            $label = mb_scrub($label, 'UTF-8');

            return [
                'url' => $link['url'] ?? null,
                'label' => $label,
                'active' => (bool) ($link['active'] ?? false),
            ];
        }, $links));

        return $paginator;
    }
}
