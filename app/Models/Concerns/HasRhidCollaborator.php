<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasRhidCollaborator
{
    public function collaboratorDisplayName(): string
    {
        if (filled($this->employee_name)) {
            return (string) $this->employee_name;
        }

        $legacy = $this->relationLoaded('employee')
            ? $this->employee
            : $this->employee()->first();

        if ($legacy && filled($legacy->name)) {
            return (string) $legacy->name;
        }

        return 'Colaborador';
    }

    public function collaboratorEmail(): ?string
    {
        if (filled($this->employee_email)) {
            return (string) $this->employee_email;
        }

        $legacy = $this->relationLoaded('employee')
            ? $this->employee
            : $this->employee()->first();

        if ($legacy && filled($legacy->email)) {
            return (string) $legacy->email;
        }

        return null;
    }

    /**
     * @return array{id: int|null, name: string, email: ?string}
     */
    public function collaboratorPayload(): array
    {
        return [
            'id' => $this->rhid_person_id ?? $this->company_employee_id,
            'name' => $this->collaboratorDisplayName(),
            'email' => $this->collaboratorEmail(),
        ];
    }
}
