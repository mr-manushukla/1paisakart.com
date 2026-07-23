<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlideTest extends TestCase
{
    use RefreshDatabase;

    private function slide(array $overrides = []): array
    {
        return array_merge([
            'eyebrow' => 'Sale',
            'heading' => 'Big *savings* today',
            'text' => 'Everything must go.',
            'cta_label' => 'Shop',
            'cta_to' => '/shop',
            'gradient' => 'green',
            'image' => 'https://example.com/a.jpg',
        ], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_serves_config_defaults_until_an_admin_saves(): void
    {
        $this->getJson('/api/slides')
            ->assertOk()
            ->assertJsonCount(count(config('slides.defaults')), 'data');
    }

    public function test_admin_can_replace_the_slides_and_they_serve_publicly(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/slides', ['slides' => [$this->slide(['heading' => 'Only *one* now'])]])
            ->assertOk();

        $this->getJson('/api/slides')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.heading', 'Only *one* now');
    }

    public function test_admin_can_hide_the_slider_entirely(): void
    {
        $this->actingAs($this->admin())->putJson('/api/admin/slides', ['slides' => []])->assertOk();

        // Empty must stay empty — NOT silently fall back to the config defaults.
        $this->getJson('/api/slides')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rejects_offsite_cta_links_and_unknown_gradients(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/slides', ['slides' => [$this->slide(['cta_to' => 'https://evil.test'])]])
            ->assertJsonValidationErrors('slides.0.cta_to');

        $this->actingAs($this->admin())
            ->putJson('/api/admin/slides', ['slides' => [$this->slide(['gradient' => 'bg-[url(javascript:1)]'])]])
            ->assertJsonValidationErrors('slides.0.gradient');
    }

    public function test_refuses_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->putJson('/api/admin/slides', ['slides' => []])
            ->assertForbidden();
    }

    public function test_refuses_guests(): void
    {
        $this->putJson('/api/admin/slides', ['slides' => []])->assertUnauthorized();
    }
}
