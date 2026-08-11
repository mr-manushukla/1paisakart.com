<?php

namespace Tests\Feature;

use App\Http\Middleware\SitePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The private-site gate has to cover the API too, or it's decorative. */
class SitePasswordTest extends TestCase
{
    use RefreshDatabase;

    private bool $stubbedSpa = false;

    /**
     * The SPA shell only exists after a frontend build, so stand one in — but
     * never clobber or delete a real build someone has staged locally.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(public_path('spa.html'))) {
            file_put_contents(public_path('spa.html'), '<html><body><div id="app"></div></body></html>');
            $this->stubbedSpa = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->stubbedSpa) {
            @unlink(public_path('spa.html'));
        }
        parent::tearDown();
    }

    private function gated(): void
    {
        config(['app.site_password' => 'Secret@2026!!']);
    }

    /**
     * The pass a browser would carry back after unlocking. withCredentials() is
     * needed because the test client otherwise drops cookies on JSON requests —
     * a real browser sends them on same-origin XHR without being asked.
     */
    private function withPass(): self
    {
        return $this->withCredentials()->withUnencryptedCookie(SitePassword::COOKIE, SitePassword::token());
    }

    public function test_without_a_password_configured_the_site_stays_public(): void
    {
        config(['app.site_password' => '']);

        $this->get('/')->assertOk();
        $this->getJson('/api/categories')->assertOk();
    }

    public function test_the_gate_blocks_the_spa(): void
    {
        $this->gated();

        $this->get('/')->assertStatus(401)->assertSee('This site is private.');
    }

    /** The obvious way round a front-end gate is to skip the front end. */
    public function test_the_gate_blocks_the_api(): void
    {
        $this->gated();

        // Straight at the API, no browser involved.
        $this->getJson('/api/categories')->assertStatus(401);
        $this->getJson('/api/products')->assertStatus(401);
        $this->postJson('/api/login', ['login' => 'a@b.c', 'password' => 'x'])->assertStatus(401);

        // ...and with a forged pass.
        $this->withCredentials()->withUnencryptedCookie(SitePassword::COOKIE, 'not-the-real-token')
            ->getJson('/api/categories')->assertStatus(401);
    }

    /**
     * The unlock must survive on requests Sanctum does NOT treat as stateful —
     * a session-backed gate 401s every one of those and the site looks broken.
     */
    public function test_the_right_password_unlocks_the_spa_and_the_api(): void
    {
        $this->gated();

        $response = $this->post('/__unlock', ['site_password' => 'Secret@2026!!', 'redirect' => '/shop'])
            ->assertRedirect('/shop');

        $this->assertSame(
            SitePassword::token(),
            $response->getCookie(SitePassword::COOKIE, false)?->getValue(),
            'the pass must go back unencrypted, or the api group cannot read it',
        );

        $this->withPass()->get('/')->assertOk()->assertSee('id="app"', false);
        $this->withPass()->getJson('/api/categories')->assertOk();
        $this->withPass()->getJson('/api/products')->assertOk();
    }

    /** Change the password and every pass already handed out stops working. */
    public function test_changing_the_password_revokes_existing_passes(): void
    {
        $this->gated();
        $old = SitePassword::token();

        config(['app.site_password' => 'Different@2026!!']);

        $this->withUnencryptedCookie(SitePassword::COOKIE, $old)->get('/')->assertStatus(401);
    }

    public function test_a_wrong_password_does_not_unlock_anything(): void
    {
        $this->gated();

        $this->post('/__unlock', ['site_password' => 'nope', 'redirect' => '/shop'])
            ->assertRedirect('/shop?locked=1');

        $this->get('/')->assertStatus(401);
        $this->get('/shop?locked=1')->assertStatus(401)->assertSee("That password isn't right", false);
    }

    /**
     * The redirect comes off a form field, so it must never carry the browser
     * to another host — including via the backslash trick browsers normalise.
     */
    public function test_the_redirect_cannot_be_pointed_off_site(): void
    {
        $this->gated();

        foreach (['//evil.example', 'https://evil.example/x', '\\\\evil.example', '/\\evil.example'] as $hostile) {
            $location = $this->post('/__unlock', ['site_password' => 'Secret@2026!!', 'redirect' => $hostile])
                ->headers->get('Location');

            $this->assertSame(
                config('app.url'),
                rtrim(parse_url($location, PHP_URL_SCHEME).'://'.parse_url($location, PHP_URL_HOST)
                    .(parse_url($location, PHP_URL_PORT) ? ':'.parse_url($location, PHP_URL_PORT) : ''), '/'),
                "redirect for [$hostile] left the site: $location",
            );
        }
    }

    /** Uptime checks and the token-guarded deploy hook must survive the gate. */
    public function test_the_health_check_stays_reachable(): void
    {
        $this->gated();

        $this->get('/up')->assertOk();
    }

    /** Logging in behind the gate must still work normally. */
    public function test_a_real_login_works_once_unlocked(): void
    {
        $this->gated();
        User::factory()->create(['email' => 'gate@test.local', 'role' => 'customer']);

        $this->withPass()
            ->withHeader('Origin', 'http://'.config('sanctum.stateful')[0])
            ->postJson('/api/login', ['login' => 'gate@test.local', 'password' => 'password'])
            ->assertOk();
    }
}
