<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\AdminController;
use App\Models\User;
use App\Models\TransactionModel;
use App\Models\StatusTransactionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AdminControllerTest extends TestCase
{
    protected $adminController;
    protected $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminController = new AdminController();
        
        // Create a mock admin user
        $this->mockUser = Mockery::mock(User::class)->makePartial();
        $this->mockUser->shouldReceive('getAttribute')
            ->with('is_admin')
            ->andReturn(true);

        // Mock Auth guard
        $mockGuard = Mockery::mock(\Illuminate\Contracts\Auth\Guard::class);
        Auth::shouldReceive('guard')
            ->andReturn($mockGuard);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test successful admin login
     */
    public function test_login_successful()
    {
        // Create request data
        $requestData = [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ];

        // Mock request
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')
            ->once()
            ->with([
                'email' => 'required|email',
                'password' => 'required'
            ])
            ->andReturn(true);

        $request->shouldReceive('only')
            ->once()
            ->with('email', 'password')
            ->andReturn($requestData);

        // Mock JWTAuth
        $token = 'mock.jwt.token';
        JWTAuth::shouldReceive('attempt')
            ->once()
            ->with($requestData)
            ->andReturn($token);

        JWTAuth::shouldReceive('user')
            ->once()
            ->andReturn($this->mockUser);

        JWTAuth::shouldReceive('factory->getTTL')
            ->once()
            ->andReturn(60);

        // Call the method
        $response = $this->adminController->login($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Login successful', $responseData->message);
        $this->assertEquals($token, $responseData->data->access_token);
        $this->assertEquals('bearer', $responseData->data->token_type);
        $this->assertEquals(3600, $responseData->data->expires_in);
        $this->assertEquals(1, $responseData->data->status);
    }

    /**
     * Test login with invalid credentials
     */
    public function test_login_invalid_credentials()
    {
        // Create request data
        $requestData = [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword'
        ];

        // Mock request
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')
            ->once()
            ->with([
                'email' => 'required|email',
                'password' => 'required'
            ])
            ->andReturn(true);

        $request->shouldReceive('only')
            ->once()
            ->with('email', 'password')
            ->andReturn($requestData);

        // Mock JWTAuth to return false
        JWTAuth::shouldReceive('attempt')
            ->once()
            ->with($requestData)
            ->andReturn(false);

        // Call the method
        $response = $this->adminController->login($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Invalid credentials', $responseData->message);
    }

    /**
     * Test login with non-admin user
     */
    public function test_login_non_admin_user()
    {
        // Create request data
        $requestData = [
            'email' => 'user@example.com',
            'password' => 'password123'
        ];

        // Mock request
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')
            ->once()
            ->with([
                'email' => 'required|email',
                'password' => 'required'
            ])
            ->andReturn(true);

        $request->shouldReceive('only')
            ->once()
            ->with('email', 'password')
            ->andReturn($requestData);

        // Mock non-admin user
        $nonAdminUser = Mockery::mock(User::class)->makePartial();
        $nonAdminUser->shouldReceive('is_admin')->andReturn(false);

        // Mock JWTAuth
        $token = 'mock.jwt.token';
        JWTAuth::shouldReceive('attempt')
            ->once()
            ->with($requestData)
            ->andReturn($token);

        JWTAuth::shouldReceive('user')
            ->once()
            ->andReturn($nonAdminUser);

        // Call the method
        $response = $this->adminController->login($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Unauthorized access', $responseData->message);
    }

    /**
     * Test successful logout
     */
    public function test_logout_successful()
    {
        // Mock JWTAuth
        $token = 'mock.jwt.token';
        JWTAuth::shouldReceive('getToken')
            ->once()
            ->andReturn($token);

        JWTAuth::shouldReceive('invalidate')
            ->once()
            ->with($token)
            ->andReturn(true);

        JWTAuth::shouldReceive('setToken')
            ->once()
            ->with(null)
            ->andReturn(true);

        // Call the method
        $response = $this->adminController->logout();
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Successfully logged out', $responseData->message);
    }

    /**
     * Test get transactions with valid token
     */
    public function test_get_transactions_with_valid_token()
    {
        // Create request with pagination parameters
        $params = [
            'per_page' => 10,
            'page' => 1,
            'search' => 'deposit'
        ];

        // Mock request
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')
            ->with('per_page', 10)
            ->andReturn($params['per_page']);
        $request->shouldReceive('input')
            ->with('search')
            ->andReturn($params['search']);

        // Mock JWTAuth
        $token = 'mock.jwt.token';
        JWTAuth::shouldReceive('getToken')
            ->once()
            ->andReturn($token);

        JWTAuth::shouldReceive('authenticate')
            ->once()
            ->with($token)
            ->andReturn($this->mockUser);

        // Mock StatusTransaction
        $mockStatus = Mockery::mock(StatusTransactionModel::class)->makePartial();
        $mockStatus->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Success');

        // Create mock transaction data
        $mockTransaction = new \stdClass();
        $mockTransaction->id = 1;
        $mockTransaction->type_transaction = 1;
        $mockTransaction->amount = 1000.00;
        $mockTransaction->description = 'Test deposit';
        $mockTransaction->transaction_date = '2024-03-27 10:00:00';
        $mockTransaction->statusTransaction = $mockStatus;

        // Create mock paginator
        $mockPaginator = Mockery::mock(\Illuminate\Pagination\LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('through')
            ->andReturn(collect([
                [
                    'id' => 1,
                    'type' => 'Deposit',
                    'amount' => '1,000.00',
                    'description' => 'Test deposit',
                    'status' => 'Success',
                    'date' => '2024-03-27 10:00:00'
                ]
            ]));
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(1);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('url')->andReturn('http://localhost');
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('perPage')->andReturn(10);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('lastItem')->andReturn(1);
        $mockPaginator->shouldReceive('total')->andReturn(1);

        // Mock query builder
        $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $mockQueryBuilder->shouldReceive('when')
            ->once()
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('orderBy')
            ->once()
            ->with('created_at', 'desc')
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('paginate')
            ->once()
            ->with(10)
            ->andReturn($mockPaginator);

        // Mock TransactionModel
        $mockTransactionModel = Mockery::mock('overload:' . TransactionModel::class);
        $mockTransactionModel->shouldReceive('with')
            ->once()
            ->with('statusTransaction')
            ->andReturn($mockQueryBuilder);

        // Call the method
        $response = $this->adminController->getTransactions($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Success', $responseData->message);
        $this->assertEquals(1, $responseData->data->status);
    }

    /**
     * Test get transactions with expired token
     */
    public function test_get_transactions_with_expired_token()
    {
        // Create request
        $request = Mockery::mock(Request::class);

        // Mock JWTAuth to throw TokenExpiredException
        $token = 'mock.jwt.token';
        JWTAuth::shouldReceive('getToken')
            ->once()
            ->andReturn($token);

        JWTAuth::shouldReceive('authenticate')
            ->once()
            ->with($token)
            ->andThrow(new TokenExpiredException());

        // Call the method
        $response = $this->adminController->getTransactions($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals((object)['message' => 'Token has expired', 'status' => 0, 'error_code' => 'TOKEN_EXPIRED'], $responseData->message);
    }

    /**
     * Test get transactions with invalid token
     */
    public function test_get_transactions_with_invalid_token()
    {
        // Create request
        $request = Mockery::mock(Request::class);

        // Mock JWTAuth to throw TokenInvalidException
        $token = 'mock.jwt.token';
        JWTAuth::shouldReceive('getToken')
            ->once()
            ->andReturn($token);

        JWTAuth::shouldReceive('authenticate')
            ->once()
            ->with($token)
            ->andThrow(new TokenInvalidException());

        // Call the method
        $response = $this->adminController->getTransactions($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals((object)['message' => 'Token is invalid', 'status' => 0, 'error_code' => 'TOKEN_INVALID'], $responseData->message);
    }

    /**
     * Test get transactions with non-admin user
     */
    public function test_get_transactions_with_non_admin_user()
    {
        // Create request
        $request = Mockery::mock(Request::class);

        // Mock non-admin user
        $nonAdminUser = Mockery::mock(User::class)->makePartial();
        $nonAdminUser->shouldReceive('is_admin')->andReturn(false);

        // Mock JWTAuth
        $token = 'mock.jwt.token';
        JWTAuth::shouldReceive('getToken')
            ->once()
            ->andReturn($token);

        JWTAuth::shouldReceive('authenticate')
            ->once()
            ->with($token)
            ->andReturn($nonAdminUser);

        // Call the method
        $response = $this->adminController->getTransactions($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals((object)['message' => 'Unauthorized access. Admin only', 'status' => 0, 'error_code' => 'NOT_ADMIN'], $responseData->message);
    }
} 