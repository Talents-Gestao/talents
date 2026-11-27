<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use Inertia\Testing\AssertableInertia;

class QuestionnaireDuplicatePermissionTest extends InterviewTestCase
{
    public function test_duplicate_button_is_hidden_and_refused_without_create(): void
    {
        $questionnaire = $this->questionnaire();

        $viewer = $this->adminWith(['entrevistas_roteiros' => ['view', 'edit']]);
        $this->actingAs($viewer)
            ->get(route('admin.entrevistas.roteiros.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.duplicate', false));
        $this->actingAs($viewer)
            ->get(route('admin.entrevistas.roteiros.edit', $questionnaire))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.duplicate', false));
        $this->actingAs($viewer)->post(route('admin.entrevistas.roteiros.duplicate', $questionnaire))->assertForbidden();

        $creator = $this->adminWith(['entrevistas_roteiros' => ['view', 'create']]);
        $this->actingAs($creator)
            ->get(route('admin.entrevistas.roteiros.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.duplicate', true));

        // O componente só mostra o botão com can.duplicate.
        $index = file_get_contents(resource_path('js/Pages/Admin/Interviews/Scripts/Index.vue'));
        $edit = file_get_contents(resource_path('js/Pages/Admin/Interviews/Scripts/Edit.vue'));
        $this->assertMatchesRegularExpression('/v-if="can\.duplicate"[\s\S]{0,200}@click="duplicate\(q\.id\)"/', $index);
        $this->assertStringContainsString('v-if="questionnaire && can.duplicate"', $edit);
    }
}
