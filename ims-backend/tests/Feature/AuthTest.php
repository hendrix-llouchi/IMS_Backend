<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\OwnerSeeder;
use App\Models\User;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // Seeded owner credentials — mirrors OwnerSeeder exactly
    // ──────────────────────────────────────────────────────────────
    private const OWNER_USERNAME = 'owner';
    private const OWNER_PASSWORD = 'owner1234';
    private const OWNER_EMAIL    = 'owner@ims.com';

    // Generic messages the application must return
    private const GENERIC_CREDENTIALS_MSG    = 'Invalid credentials.';
    private const GENERIC_FORGOT_PASSWORD_MSG = 'If this email exists in our system you will receive a password reset link shortly.';
    private const LOCKED_MSG                 = 'Account temporarily locked. Try again in 15 minutes.';

    // Cookie name used for the JWT
    private const TOKEN_COOKIE = 'token';

    /**
     * Seed the owner account before every test so every test is self-contained.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable the login throttle middleware during tests so rate-limiting
        // does not interfere with tests that fire multiple rapid requests.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->seed(OwnerSeeder::class);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: perform a login and return the signed JWT string
    // ──────────────────────────────────────────────────────────────
    private function getOwnerToken(): string
    {
        $user  = User::where('username', self::OWNER_USERNAME)->firstOrFail();
        return JWTAuth::fromUser($user);
    }

    // ──────────────────────────────────────────────────────────────
    // 1. test_login_with_correct_credentials
    // ──────────────────────────────────────────────────────────────
    public function test_login_with_correct_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => self::OWNER_USERNAME,
            'password' => self::OWNER_PASSWORD,
        ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. test_login_returns_jwt_cookie
    // ──────────────────────────────────────────────────────────────
    public function test_login_returns_jwt_cookie(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => self::OWNER_USERNAME,
            'password' => self::OWNER_PASSWORD,
        ]);

        $response->assertStatus(200);
        $response->assertCookie(self::TOKEN_COOKIE);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. test_login_with_wrong_password
    // ──────────────────────────────────────────────────────────────
    public function test_login_with_wrong_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => self::OWNER_USERNAME,
            'password' => 'totally-wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => self::GENERIC_CREDENTIALS_MSG]);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. test_login_with_wrong_username
    // ──────────────────────────────────────────────────────────────
    public function test_login_with_wrong_username(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'nonexistent_user',
            'password' => self::OWNER_PASSWORD,
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => self::GENERIC_CREDENTIALS_MSG]);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. test_login_increments_failed_attempts
    // ──────────────────────────────────────────────────────────────
    public function test_login_increments_failed_attempts(): void
    {
        $this->postJson('/api/auth/login', [
            'username' => self::OWNER_USERNAME,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->assertDatabaseHas('users', [
            'username'        => self::OWNER_USERNAME,
            'failed_attempts' => 1,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. test_account_locks_after_3_failed_attempts
    // ──────────────────────────────────────────────────────────────
    public function test_account_locks_after_3_failed_attempts(): void
    {
        // First two wrong attempts
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/auth/login', [
                'username' => self::OWNER_USERNAME,
                'password' => 'wrong-password',
            ]);
        }

        // Third wrong attempt should trigger the lock and return 423
        $response = $this->postJson('/api/auth/login', [
            'username' => self::OWNER_USERNAME,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(423);

        // locked_until must now be set in the database
        $user = User::where('username', self::OWNER_USERNAME)->firstOrFail();
        $this->assertNotNull($user->locked_until, 'locked_until should not be null after 3 failed attempts');
        $this->assertTrue(
            Carbon::now()->lessThan($user->locked_until),
            'locked_until should be in the future'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // 7. test_locked_account_cannot_login
    // ──────────────────────────────────────────────────────────────
    public function test_locked_account_cannot_login(): void
    {
        // Manually lock the account
        User::where('username', self::OWNER_USERNAME)->update([
            'locked_until'    => Carbon::now()->addMinutes(15),
            'failed_attempts' => 3,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => self::OWNER_USERNAME,
            'password' => self::OWNER_PASSWORD,  // correct password — still blocked
        ]);

        $response->assertStatus(423);
        $response->assertJson(['message' => self::LOCKED_MSG]);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. test_logout_clears_cookie
    // ──────────────────────────────────────────────────────────────
    public function test_logout_clears_cookie(): void
    {
        $token = $this->getOwnerToken();

        $response = $this->withCookie(self::TOKEN_COOKIE, $token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);

        // Laravel sets the cookie expiration to past when "forgetting" it.
        // assertCookieExpired verifies the cookie is present in the response
        // with an expired/past date, confirming it has been cleared.
        $response->assertCookieExpired(self::TOKEN_COOKIE);
    }

    // ──────────────────────────────────────────────────────────────
    // 9. test_change_password_success
    // ──────────────────────────────────────────────────────────────
    public function test_change_password_success(): void
    {
        $token = $this->getOwnerToken();

        $response = $this->withCookie(self::TOKEN_COOKIE, $token)
            ->postJson('/api/auth/change-password', [
                'username'     => self::OWNER_USERNAME,
                'new_password' => 'newSecurePass123',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Password changed successfully. Please log in with your new password.']);
    }

    // ──────────────────────────────────────────────────────────────
    // 10. test_change_password_requires_username
    // ──────────────────────────────────────────────────────────────
    public function test_change_password_requires_username(): void
    {
        $token = $this->getOwnerToken();

        // Send request without the required "username" field
        $response = $this->withCookie(self::TOKEN_COOKIE, $token)
            ->postJson('/api/auth/change-password', [
                'new_password' => 'newSecurePass123',
                // "username" intentionally omitted
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['username']);
    }

    // ──────────────────────────────────────────────────────────────
    // 11. test_forgot_password_returns_generic_message_for_real_email
    // ──────────────────────────────────────────────────────────────
    public function test_forgot_password_returns_generic_message_for_real_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => self::OWNER_EMAIL,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => self::GENERIC_FORGOT_PASSWORD_MSG]);
    }

    // ──────────────────────────────────────────────────────────────
    // 12. test_forgot_password_returns_generic_message_for_fake_email
    // ──────────────────────────────────────────────────────────────
    public function test_forgot_password_returns_generic_message_for_fake_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@doesnotexist.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => self::GENERIC_FORGOT_PASSWORD_MSG]);
    }

    // ──────────────────────────────────────────────────────────────
    // 13. test_forgot_password_generates_reset_token
    // ──────────────────────────────────────────────────────────────
    public function test_forgot_password_generates_reset_token(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => self::OWNER_EMAIL,
        ]);

        $response->assertStatus(200);

        $user = User::where('email', self::OWNER_EMAIL)->firstOrFail();
        $this->assertNotNull($user->reset_token, 'reset_token should not be null after forgot-password request');
        $this->assertNotNull($user->reset_token_expires_at, 'reset_token_expires_at should not be null');
        $this->assertTrue(
            Carbon::now()->lessThan($user->reset_token_expires_at),
            'reset_token_expires_at should be in the future (1 hour from now)'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // 14. test_reset_password_with_valid_token
    // ──────────────────────────────────────────────────────────────
    public function test_reset_password_with_valid_token(): void
    {
        $validToken = bin2hex(random_bytes(32));

        User::where('email', self::OWNER_EMAIL)->update([
            'reset_token'            => $validToken,
            'reset_token_expires_at' => Carbon::now()->addHour(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token'        => $validToken,
            'new_password' => 'ResetPassword@123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Password reset successful. Please log in with your new password.']);
    }

    // ──────────────────────────────────────────────────────────────
    // 15. test_reset_password_with_expired_token
    // ──────────────────────────────────────────────────────────────
    public function test_reset_password_with_expired_token(): void
    {
        $expiredToken = bin2hex(random_bytes(32));

        User::where('email', self::OWNER_EMAIL)->update([
            'reset_token'            => $expiredToken,
            'reset_token_expires_at' => Carbon::now()->subHour(), // already expired
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token'        => $expiredToken,
            'new_password' => 'AnyNewPass@123',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'This reset link has expired or is invalid. Please request a new one.']);
    }

    // ──────────────────────────────────────────────────────────────
    // 16. test_reset_password_with_invalid_token
    // ──────────────────────────────────────────────────────────────
    public function test_reset_password_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'token'        => 'this-token-does-not-exist-in-the-database',
            'new_password' => 'AnyNewPass@123',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'This reset link has expired or is invalid. Please request a new one.']);
    }

    // ──────────────────────────────────────────────────────────────
    // 17. test_reset_password_invalidates_token_after_use
    // ──────────────────────────────────────────────────────────────
    public function test_reset_password_invalidates_token_after_use(): void
    {
        $token = bin2hex(random_bytes(32));

        User::where('email', self::OWNER_EMAIL)->update([
            'reset_token'            => $token,
            'reset_token_expires_at' => Carbon::now()->addHour(),
        ]);

        // First use — should succeed
        $this->postJson('/api/auth/reset-password', [
            'token'        => $token,
            'new_password' => 'FirstResetPass@1',
        ])->assertStatus(200);

        // Verify the token is now null in the database
        $this->assertDatabaseHas('users', [
            'email'       => self::OWNER_EMAIL,
            'reset_token' => null,
        ]);

        // Second use with the same token — must fail with 400
        $this->postJson('/api/auth/reset-password', [
            'token'        => $token,
            'new_password' => 'SecondResetPass@2',
        ])->assertStatus(400);
    }
}
