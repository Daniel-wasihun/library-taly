<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularLibrarian;
    protected Role $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $librarianRole = Role::firstOrCreate(['name' => 'Librarian']);

        $this->superAdmin = User::factory()->create([
            'name' => 'Dr. Abebe Bekele',
            'username' => 'admin',
            'email' => 'admin@unilib.edu',
        ]);
        $this->superAdmin->assignRole($this->superAdminRole);

        $this->regularLibrarian = User::factory()->create([
            'name' => 'Kassahun Desta',
            'username' => 'kassahun',
            'email' => 'kassahun@unilib.edu',
        ]);
        $this->regularLibrarian->assignRole($librarianRole);
    }

    public function test_super_admin_is_not_visible_in_user_list(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonMissing([
            'username' => 'admin',
            'name' => 'Dr. Abebe Bekele',
        ]);
        $response->assertJsonFragment([
            'username' => 'kassahun',
            'name' => 'Kassahun Desta',
        ]);
    }

    public function test_super_admin_account_cannot_be_updated_by_any_means(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/users/{$this->superAdmin->id}", [
                'name' => 'Hacked Admin',
                'username' => 'hacked_admin',
                'email' => 'hacked@unilib.edu',
                'role' => 'Desk Librarian',
                'status' => 'active',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
            'username' => 'admin',
        ]);
    }

    public function test_super_admin_account_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->regularLibrarian)
            ->deleteJson("/api/users/{$this->superAdmin->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
        ]);
    }

    public function test_super_admin_permissions_cannot_be_overridden(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/users/{$this->superAdmin->id}/permissions", [
                'permissions' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_role_cannot_be_modified(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/roles/{$this->superAdminRole->id}", [
                'name' => 'Changed Admin',
                'permissions' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/roles/{$this->superAdminRole->id}");

        $response->assertStatus(422);
    }

    public function test_super_admin_has_all_permissions_and_abilities(): void
    {
        $this->assertTrue($this->superAdmin->isSuperAdmin());
        $this->assertTrue($this->superAdmin->can('any.arbitrary.permission'));
        $this->assertTrue($this->superAdmin->hasPermissionTo('any.arbitrary.permission'));
        $this->assertTrue($this->superAdmin->canManageAllLibraries());
    }
}
