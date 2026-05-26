<?php

namespace Database\Factories;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Support\WorkspaceManager;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->workspaces()->exists()) {
                return;
            }

            if ($user->role === UserRole::SuperAdmin) {
                return;
            }

            if ($user->company_id) {
                UserWorkspace::create([
                    'user_id' => $user->id,
                    'workspace_type' => WorkspaceType::Company,
                    'company_id' => $user->company_id,
                    'role' => $user->role,
                    'is_owner' => false,
                    'is_active' => (bool) $user->is_active,
                ]);
            }

            app(WorkspaceManager::class)->syncLegacyUserColumns($user);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_set_at' => now(),
            'remember_token' => Str::random(10),
            'role' => UserRole::CompanyUser,
            'company_id' => null,
            'is_active' => true,
            'is_commercial' => false,
            'is_owner' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SuperAdmin,
            'company_id' => null,
        ])->afterCreating(function (User $user): void {
            if ($user->workspaces()->where('workspace_type', WorkspaceType::Talents)->exists()) {
                return;
            }

            $workspace = UserWorkspace::create([
                'user_id' => $user->id,
                'workspace_type' => WorkspaceType::Talents,
                'company_id' => null,
                'role' => UserRole::SuperAdmin,
                'is_owner' => (bool) $user->is_owner,
                'is_active' => (bool) $user->is_active,
            ]);

            if ($user->role !== UserRole::SuperAdmin || $user->isOwner()) {
                return;
            }

            $perms = [];
            foreach (AdminPermissionModule::all() as $module) {
                foreach (PermissionAction::all() as $action) {
                    $perms[] = ['module' => $module->value, 'action' => $action->value];
                }
            }

            app(SyncAdminUserPermissions::class)->execute($workspace, $perms);
            app(WorkspaceManager::class)->syncLegacyUserColumns($user);
        });
    }

    public function companyAdmin(?int $companyId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CompanyAdmin,
            'company_id' => $companyId,
        ])->afterCreating(function (User $user) use ($companyId): void {
            $companyId ??= $user->company_id;

            if (! $companyId || $user->workspaces()
                ->where('workspace_type', WorkspaceType::Company)
                ->where('company_id', $companyId)
                ->exists()) {
                return;
            }

            UserWorkspace::create([
                'user_id' => $user->id,
                'workspace_type' => WorkspaceType::Company,
                'company_id' => $companyId,
                'role' => UserRole::CompanyAdmin,
                'is_owner' => false,
                'is_active' => (bool) $user->is_active,
            ]);

            app(WorkspaceManager::class)->syncLegacyUserColumns($user);
        });
    }

    public function companyUser(?int $companyId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CompanyUser,
            'company_id' => $companyId,
        ])->afterCreating(function (User $user) use ($companyId): void {
            $companyId ??= $user->company_id;

            if (! $companyId || $user->workspaces()
                ->where('workspace_type', WorkspaceType::Company)
                ->where('company_id', $companyId)
                ->exists()) {
                return;
            }

            UserWorkspace::create([
                'user_id' => $user->id,
                'workspace_type' => WorkspaceType::Company,
                'company_id' => $companyId,
                'role' => UserRole::CompanyUser,
                'is_owner' => false,
                'is_active' => (bool) $user->is_active,
            ]);

            app(WorkspaceManager::class)->syncLegacyUserColumns($user);
        });
    }

    public function pendingRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_set_at' => null,
        ]);
    }
}
