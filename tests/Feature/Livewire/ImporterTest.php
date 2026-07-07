<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Importer;
use App\Models\Import;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImporterTest extends TestCase
{
    public function test_renders_successfully()
    {
        Livewire::actingAs(User::factory()->canImport()->create())
            ->test(Importer::class)
            ->assertStatus(200);
    }

    public function test_requires_permission()
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Importer::class)
            ->assertStatus(403);
    }

    public function test_bulk_deletes_owned_imports()
    {
        Storage::fake();
        $user = User::factory()->canImport()->create();
        $imports = Import::factory()->count(3)->create(['created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Importer::class)
            ->set('selectedIds', $imports->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('bulkDestroy')
            ->assertSet('message_type', 'success');

        foreach ($imports as $import) {
            $this->assertDatabaseMissing('imports', ['id' => $import->id]);
        }
    }

    public function test_bulk_destroy_skips_imports_the_caller_does_not_own()
    {
        Storage::fake();
        $me = User::factory()->canImport()->create();
        $someoneElse = User::factory()->canImport()->create();

        $mine = Import::factory()->create(['created_by' => $me->id]);
        $theirs = Import::factory()->create(['created_by' => $someoneElse->id]);

        Livewire::actingAs($me)
            ->test(Importer::class)
            ->set('selectedIds', [(string) $mine->id, (string) $theirs->id])
            ->call('bulkDestroy')
            ->assertSet('message_type', 'success');

        $this->assertDatabaseMissing('imports', ['id' => $mine->id]);
        $this->assertDatabaseHas('imports', ['id' => $theirs->id]);
    }

    public function test_bulk_destroy_all_denied_produces_error_message()
    {
        Storage::fake();
        $me = User::factory()->canImport()->create();
        $someoneElse = User::factory()->canImport()->create();

        $theirs = Import::factory()->count(2)->create(['created_by' => $someoneElse->id]);

        Livewire::actingAs($me)
            ->test(Importer::class)
            ->set('selectedIds', $theirs->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('bulkDestroy')
            ->assertSet('message_type', 'danger');

        // Neither import was deleted.
        foreach ($theirs as $import) {
            $this->assertDatabaseHas('imports', ['id' => $import->id]);
        }
    }

    public function test_superuser_can_bulk_delete_anyone_elses_imports()
    {
        Storage::fake();
        $superuser = User::factory()->superuser()->create();
        $owner = User::factory()->canImport()->create();
        $imports = Import::factory()->count(2)->create(['created_by' => $owner->id]);

        Livewire::actingAs($superuser)
            ->test(Importer::class)
            ->set('selectedIds', $imports->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('bulkDestroy')
            ->assertSet('message_type', 'success');

        foreach ($imports as $import) {
            $this->assertDatabaseMissing('imports', ['id' => $import->id]);
        }
    }

    public function test_bulk_destroy_with_no_selection_does_nothing()
    {
        Storage::fake();
        $user = User::factory()->canImport()->create();
        Import::factory()->create(['created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Importer::class)
            ->call('bulkDestroy')
            ->assertSet('message', null);

        $this->assertDatabaseCount('imports', 1);
    }

    public function test_files_paginate_by_per_page()
    {
        $user = User::factory()->canImport()->create();
        Import::factory()->count(30)->create(['created_by' => $user->id]);

        $component = Livewire::actingAs($user)->test(Importer::class);

        // Default $perPage = 25; 30 imports means 25 on page 1, 5 on page 2.
        $this->assertCount(25, $component->instance()->files->items());
        $this->assertSame(30, $component->instance()->files->total());
        $this->assertTrue($component->instance()->files->hasPages());
    }

    public function test_select_all_selects_only_current_page_deletable_rows()
    {
        $me = User::factory()->canImport()->create();
        $someoneElse = User::factory()->canImport()->create();

        // 5 mine + 3 theirs = 8 imports on page 1 (fits under the 25 default).
        $mine = Import::factory()->count(5)->create(['created_by' => $me->id]);
        Import::factory()->count(3)->create(['created_by' => $someoneElse->id]);

        $component = Livewire::actingAs($me)
            ->test(Importer::class)
            ->set('selectAll', true);

        // Only the 5 the caller can delete are picked up.
        $selected = $component->get('selectedIds');
        $this->assertCount(5, $selected);
        $this->assertEquals(
            $mine->pluck('id')->sort()->values()->all(),
            collect($selected)->map(fn ($id) => (int) $id)->sort()->values()->all()
        );
    }

    public function test_changing_page_clears_selection()
    {
        $user = User::factory()->canImport()->create();
        Import::factory()->count(30)->create(['created_by' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(Importer::class)
            ->set('selectAll', true);

        $this->assertNotEmpty($component->get('selectedIds'));

        // Livewire's WithPagination exposes a setPage() method; calling it
        // triggers the updatedPage hook where we clear selection.
        $component->call('setPage', 2);

        $this->assertSame([], $component->get('selectedIds'));
        $this->assertFalse($component->get('selectAll'));
    }
}
