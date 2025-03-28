<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Config;

class AuthControllerTest extends TestCase
{
    protected $authController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authController = new AuthController();
    }

    /**
     * Test successful login and token generation
     *
     * @return void
     */
    public function test_login_returns_valid_token()
    {
        // Set up the config value for token_name
        Config::set('app.token_name', 'nur_muhammad_abdul_falaq');

        // Call the login method
        $response = $this->authController->login();
        $responseData = $response->getData();

        // Assert response structure
        $this->assertTrue(isset($responseData->access_token));
        $this->assertTrue(isset($responseData->token_type));
        $this->assertTrue(isset($responseData->expires_in));

        // Assert token type
        $this->assertEquals('bearer', $responseData->token_type);

        // Assert expiration time
        $this->assertEquals(3600, $responseData->expires_in);

        // Decode and verify token format
        $token = $responseData->access_token;
        $decodedToken = base64_decode($token);
        $this->assertStringStartsWith('nur_muhammad_abdul_falaq_', $decodedToken);
    }

    /**
     * Test login failure when config is not set
     *
     * @return void
     */
    public function test_login_handles_config_missing()
    {
        // Clear the config value
        Config::set('app.token_name', null);

        // Call the login method
        $response = $this->authController->login();
        $responseData = $response->getData();

        // Assert error response
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Login failed', $responseData->message);
        $this->assertEquals('error', $responseData->status);
    }
} 