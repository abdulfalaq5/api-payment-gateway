<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\WithdrawalController;
use App\Repositories\TransactionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;

class WithdrawalControllerTest extends TestCase
{
    protected $withdrawalController;
    protected $transactionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);
        $this->withdrawalController = new WithdrawalController($this->transactionRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test successful withdrawal
     */
    public function test_store_successful_withdrawal()
    {
        // Create request data
        $requestData = [
            'amount' => 1000.00
        ];
        $request = new Request($requestData);

        // Mock repository response
        $expectedResponse = [
            'success' => true,
            'data' => [
                'amount' => 1000.00,
                'new_amount' => 9000.00,
                'status' => 1
            ]
        ];
        
        $this->transactionRepository->shouldReceive('withdrawalAmount')
            ->once()
            ->with(Mockery::type(Request::class))
            ->andReturn($expectedResponse);

        // Call the method
        $response = $this->withdrawalController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Withdrawal successfully', $responseData->message);
        $this->assertEquals($expectedResponse['data']['amount'], $responseData->data->amount);
        $this->assertEquals($expectedResponse['data']['new_amount'], $responseData->data->new_amount);
        $this->assertEquals($expectedResponse['data']['status'], $responseData->data->status);
    }

    /**
     * Test withdrawal with invalid amount
     */
    public function test_store_validates_amount()
    {
        // Create invalid request data
        $requestData = [
            'amount' => -100 // Invalid: negative amount
        ];
        $request = new Request($requestData);

        // Call the method
        $response = $this->withdrawalController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }

    /**
     * Test withdrawal with missing amount
     */
    public function test_store_validates_required_amount()
    {
        // Create invalid request data
        $requestData = []; // Missing amount
        $request = new Request($requestData);

        // Call the method
        $response = $this->withdrawalController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }

    /**
     * Test withdrawal with repository error
     */
    public function test_store_handles_repository_error()
    {
        // Create request data
        $requestData = [
            'amount' => 1000.00
        ];
        $request = new Request($requestData);

        // Mock repository to return error
        $errorResponse = [
            'success' => false,
            'message' => 'Insufficient balance'
        ];
        
        $this->transactionRepository->shouldReceive('withdrawalAmount')
            ->once()
            ->with(Mockery::type(Request::class))
            ->andReturn($errorResponse);

        // Call the method
        $response = $this->withdrawalController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Insufficient balance', $responseData->message);
    }

    /**
     * Test withdrawal with unexpected error
     */
    public function test_store_handles_unexpected_error()
    {
        // Create request data
        $requestData = [
            'amount' => 1000.00
        ];
        $request = new Request($requestData);

        // Mock repository to throw exception
        $this->transactionRepository->shouldReceive('withdrawalAmount')
            ->once()
            ->with(Mockery::type(Request::class))
            ->andThrow(new \Exception('Database error'));

        // Call the method
        $response = $this->withdrawalController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Failed to withdrawal amount', $responseData->message);
    }

    /**
     * Test withdrawal with non-numeric amount
     */
    public function test_store_validates_numeric_amount()
    {
        // Create invalid request data
        $requestData = [
            'amount' => 'not-a-number' // Invalid: non-numeric amount
        ];
        $request = new Request($requestData);

        // Call the method
        $response = $this->withdrawalController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }
} 