<?php

namespace Tests\Feature;

use App\Models\Alumni;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlumniScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_fakultas_index_is_scoped_to_its_faculty(): void
    {
        $user = $this->makeUser('admin_fakultas', [
            'fakultas' => 'Teknik',
        ]);

        $allowed = $this->makeAlumni('1001', 'Fakultas Teknik', 'Informatika');
        $this->makeAlumni('1002', 'Fakultas Ekonomi', 'Akuntansi');

        $response = $this->actingAs($user)
            ->getJson('/api/admin/alumni?per_page=all')
            ->assertOk();

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertSame($allowed->nim, $items[0]['nim']);
    }

    public function test_admin_prodi_index_is_scoped_to_its_study_program(): void
    {
        $user = $this->makeUser('admin_prodi', [
            'prodi' => 'Informatika',
        ]);

        $allowed = $this->makeAlumni('2001', 'Fakultas Teknik', 'Informatika');
        $this->makeAlumni('2002', 'Fakultas Teknik', 'Sistem Informasi');

        $response = $this->actingAs($user)
            ->getJson('/api/admin/alumni?per_page=all')
            ->assertOk();

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertSame($allowed->nim, $items[0]['nim']);
    }

    public function test_admin_fakultas_cannot_view_alumni_outside_scope(): void
    {
        $user = $this->makeUser('admin_fakultas', [
            'fakultas' => 'Teknik',
        ]);
        $outside = $this->makeAlumni('3001', 'Fakultas Ekonomi', 'Akuntansi');

        $this->actingAs($user)
            ->getJson('/api/admin/alumni/'.$outside->id)
            ->assertStatus(403);
    }

    public function test_admin_prodi_cannot_update_alumni_outside_scope(): void
    {
        $user = $this->makeUser('admin_prodi', [
            'prodi' => 'Informatika',
        ]);
        $outside = $this->makeAlumni('4001', 'Fakultas Teknik', 'Akuntansi');

        $this->actingAs($user)
            ->putJson('/api/admin/alumni/'.$outside->id, [
                'nama' => 'Updated Name',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('alumnis', [
            'id' => $outside->id,
            'nama' => 'Updated Name',
        ]);
    }

    public function test_super_admin_can_delete_any_alumni(): void
    {
        $user = $this->makeUser('super_admin');
        $target = $this->makeAlumni('5001', 'Fakultas Ekonomi', 'Manajemen');

        $this->actingAs($user)
            ->deleteJson('/api/admin/alumni/'.$target->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('alumnis', [
            'id' => $target->id,
        ]);
    }

    protected function makeUser(string $roleName, array $attrs = []): User
    {
        $role = Role::firstOrCreate(['nama_role' => $roleName]);

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'fakultas' => null,
            'prodi' => null,
        ], $attrs));
    }

    protected function makeAlumni(string $nim, string $fakultas, string $prodi): Alumni
    {
        return Alumni::create([
            'nama' => 'Alumni '.$nim,
            'nim' => $nim,
            'prodi' => $prodi,
            'fakultas' => $fakultas,
            'tahun_lulus' => 2023,
            'email' => 'alumni'.$nim.'@example.com',
            'status_pekerjaan' => 'Bekerja',
        ]);
    }
}
