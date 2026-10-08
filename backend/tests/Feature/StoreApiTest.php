<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_and_product_routes_keep_the_frontend_response_shape(): void
    {
        DB::table('products')->insert($this->product());

        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonMissingPath('0.sold')
            ->assertJsonPath('0.featured', true)
            ->assertJsonPath('0.stock', 5);
    }

    public function test_registration_hashes_passwords_and_issues_a_working_bearer_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'secret-password',
        ])->assertCreated();

        $this->assertDatabaseHas('customers', [
            'email' => 'customer@example.com',
        ]);
        $this->assertTrue(Hash::check(
            'secret-password',
            DB::table('customers')->where('email', 'customer@example.com')->value('password')
        ));

        $this->withToken($response->json('token'))
            ->getJson('/api/customer/orders')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_customer_can_place_order_and_stock_is_updated_atomically(): void
    {
        DB::table('products')->insert($this->product());
        $token = $this->registerCustomer();

        $this->withToken($token)->postJson('/api/orders', [
            'items' => [['productId' => 'prod-test', 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('order.total', 200)
            ->assertJsonPath('order.items.0.quantity', 2);

        $this->assertDatabaseHas('products', [
            'id' => 'prod-test',
            'stock' => 3,
            'sold' => 2,
        ]);
        $this->withToken($token)->getJson('/api/customer/orders')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_admin_can_read_summary_and_customers_cannot(): void
    {
        $admin = $this->postJson('/api/auth/login', [
            'email' => config('access.admin_email'),
            'password' => config('access.admin_password'),
        ])->assertOk()->json('token');

        $this->withToken($admin)->getJson('/api/admin/summary')
            ->assertOk()
            ->assertJsonStructure(['summary', 'products', 'customers', 'visits', 'orders']);

        $customer = $this->registerCustomer();
        $this->withToken($customer)->getJson('/api/admin/summary')
            ->assertForbidden()
            ->assertJson(['message' => 'Admin access required.']);
    }

    public function test_legacy_plaintext_password_is_upgraded_after_a_successful_login(): void
    {
        DB::table('customers')->insert([
            'id' => 'cust-legacy',
            'name' => 'Legacy Customer',
            'email' => 'legacy@example.com',
            'password' => 'legacy-password',
            'phone' => '',
            'role' => 'customer',
            'createdAt' => now()->toIso8601String(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'legacy@example.com',
            'password' => 'legacy-password',
        ])->assertOk()->assertJsonPath('user.id', 'cust-legacy');

        $this->assertTrue(Hash::check(
            'legacy-password',
            DB::table('customers')->where('id', 'cust-legacy')->value('password')
        ));
    }

    private function registerCustomer(): string
    {
        return $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer-'.uniqid().'@example.com',
            'password' => 'secret-password',
        ])->assertCreated()->json('token');
    }

    private function product(): array
    {
        return [
            'id' => 'prod-test',
            'name' => 'Test Charger',
            'category' => 'Charging',
            'price' => 100,
            'stock' => 5,
            'sold' => 0,
            'image' => 'https://example.com/product.jpg',
            'description' => 'A test product.',
            'featured' => 1,
        ];
    }
}
