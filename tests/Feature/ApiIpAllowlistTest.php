<?php

namespace Tests\Feature;

use App\Models\ApiIpAllowlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiIpAllowlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_cidr_allowlist_and_deactivate_it_without_deleting_history(): void
    {
        $user = User::create([
            'name' => 'API Admin',
            'email' => 'api-admin@example.test',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.masterdata.api-ip-allowlists.store'), [
                'ip_address' => '203.0.113.0/24',
                'name' => 'Server pusat production',
                'note' => 'Akses sinkronisasi stok',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'IP allowlist berhasil ditambahkan');

        $allowlist = ApiIpAllowlist::firstOrFail();
        $this->assertTrue($allowlist->is_active);
        $this->assertSame($user->id, $allowlist->created_by);

        $this->actingAs($user)
            ->deleteJson(route('admin.masterdata.api-ip-allowlists.destroy', $allowlist))
            ->assertOk();

        $allowlist->refresh();
        $this->assertFalse($allowlist->is_active);
        $this->assertDatabaseCount('api_ip_allowlists', 1);
    }

    public function test_allowlist_rejects_invalid_ip_or_cidr(): void
    {
        $user = User::create([
            'name' => 'API Admin',
            'email' => 'api-invalid@example.test',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.masterdata.api-ip-allowlists.store'), [
                'ip_address' => '203.0.113.10/99',
                'name' => 'Invalid IP',
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ip_address']);
    }
}
