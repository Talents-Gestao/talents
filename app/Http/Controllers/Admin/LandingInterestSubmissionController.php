<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Leads\CreateLandingInterestSubmission;
use App\Enums\LandingInterestSource;
use App\Http\Controllers\Controller;
use App\Models\LandingInterestSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LandingInterestSubmissionController extends Controller
{
    public function index(): Response
    {
        $paginator = LandingInterestSubmission::query()
            ->with('creator:id,name')
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (LandingInterestSubmission $s) => self::submissionRow($s));

        return Inertia::render('Admin/LandingInterest/Index', [
            'submissions' => self::scrubPaginatorArray($paginator->toArray()),
            'sourceOptions' => LandingInterestSource::options(),
        ]);
    }

    public function store(Request $request, CreateLandingInterestSubmission $create): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'string', Rule::enum(LandingInterestSource::class)],
        ]);

        $create->execute($data, $request->user());

        return redirect()
            ->route('admin.landing-interest.index')
            ->with('success', 'Lead cadastrado com sucesso.');
    }

    public function update(Request $request, LandingInterestSubmission $submission): RedirectResponse
    {
        if ($request->exists('is_qualified') && $request->input('is_qualified') === '') {
            $request->merge(['is_qualified' => null]);
        }

        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:10000'],
            'is_qualified' => ['nullable', 'boolean'],
        ], [
            'admin_notes.max' => 'As anotações não podem ter mais de 10000 caracteres.',
            'is_qualified.boolean' => 'Informe se o lead está qualificado (Sim ou Não).',
        ]);

        $payload = [
            'admin_notes' => $data['admin_notes'] ?? null,
        ];

        if (array_key_exists('is_qualified', $data)) {
            $payload['is_qualified'] = $data['is_qualified'];
        }

        $submission->update($payload);

        return redirect()
            ->route('admin.landing-interest.index')
            ->with('success', 'Lead atualizado.');
    }

    public function destroy(LandingInterestSubmission $submission): RedirectResponse
    {
        $name = $submission->name;
        $submission->delete();

        return redirect()
            ->route('admin.landing-interest.index')
            ->with('success', "Lead «{$name}» excluído.");
    }

    /**
     * @return array<string, mixed>
     */
    private static function submissionRow(LandingInterestSubmission $s): array
    {
        $source = $s->sourceEnum();

        return [
            'id' => $s->id,
            'name' => self::asUtf8String($s->name, ''),
            'email' => self::asUtf8String($s->email, ''),
            'phone' => self::asUtf8String($s->phone),
            'company' => self::asUtf8String($s->company),
            'message' => self::asUtf8String($s->message),
            'admin_notes' => self::asUtf8String($s->admin_notes),
            'is_qualified' => $s->is_qualified,
            'source' => $source->value,
            'source_label' => $source->label(),
            'created_by' => $s->created_by,
            'created_by_name' => self::asUtf8String($s->creator?->name),
            'mail_sent_at' => $s->mail_sent_at?->toIso8601String(),
            'mail_error' => self::humanizeStoredMailError(self::asUtf8String($s->mail_error)),
            'created_at' => $s->created_at?->toIso8601String(),
            'updated_at' => $s->updated_at?->toIso8601String(),
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
     * Substitui erros técnicos antigos (ex.: TypeError do htmlspecialchars) por texto legível.
     */
    private static function humanizeStoredMailError(?string $error): ?string
    {
        if ($error === null || $error === '') {
            return $error;
        }

        if (str_contains($error, 'htmlspecialchars()')) {
            return 'Falha ao montar o e-mail de aviso (registro antigo; o envio já foi corrigido para novos leads).';
        }

        return $error;
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
