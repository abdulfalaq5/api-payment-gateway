<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\DepositController;
use App\Repositories\TransactionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Mockery;

class DepositControllerTest extends TestCase
{
    protected $depositController;
    protected $transactionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);
        $this->depositController = new DepositController($this->transactionRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test getting current amount successfully
     */
    public function test_index_returns_current_amount()
    {
        // Mock repository response
        $expectedAmount = ['amount' => 1000.00, 'currency' => 'IDR'];
        $this->transactionRepository->shouldReceive('getAmount')
            ->once()
            ->andReturn($expectedAmount);

        // Call the method
        $response = $this->depositController->index();
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Amount retrieved successfully', $responseData->message);
        $this->assertEquals($expectedAmount, (array)$responseData->data);
    }

    /**
     * Test getting amount with error
     */
    public function test_index_handles_error()
    {
        // Mock repository to throw exception
        $this->transactionRepository->shouldReceive('getAmount')
            ->once()
            ->andThrow(new \Exception('Database error'));

        // Call the method
        $response = $this->depositController->index();
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Failed to get amount', $responseData->message);
    }

    /**
     * Test successful deposit creation without Midtrans
     */
    public function test_store_creates_deposit_without_midtrans()
    {
        // Disable Midtrans
        Config::set('midtrans.use', false);

        // Create request data
        $requestData = [
            'order_id' => 'ORDER123',
            'amount' => 1000.00,
            'timestamp' => '2024-03-27 10:00:00'
        ];
        $request = new Request($requestData);

        // Mock repository response
        $expectedResponse = [
            'success' => true,
            'data' => [
                'order_id' => 'ORDER123',
                'amount' => 1000.00,
                'status' => 1
            ]
        ];
        
        $this->transactionRepository->shouldReceive('addDeposit')
            ->once()
            ->with(Mockery::type(Request::class), null)
            ->andReturn($expectedResponse);

        // Call the method
        $response = $this->depositController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Payment link generated successfully', $responseData->message);
        $this->assertEquals($expectedResponse['data']['order_id'], $responseData->data->order_id);
        $this->assertEquals($expectedResponse['data']['amount'], $responseData->data->amount);
        $this->assertEquals($expectedResponse['data']['status'], $responseData->data->status);
    }

    /**
     * Test deposit creation with invalid data
     */
    public function test_store_validates_request_data()
    {
        // Create invalid request data
        $requestData = [
            'order_id' => '', // Invalid: empty
            'amount' => -100, // Invalid: negative
            'timestamp' => 'invalid-date' // Invalid: wrong format
        ];
        $request = new Request($requestData);

        // Call the method
        $response = $this->depositController->store($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }

    /**
     * Test successful manual deposit creation
     */
    public function test_store_manual_creates_deposit()
    {
        // Create request data
        $requestData = [
            'order_id' => 'MANUAL123',
            'amount' => 1000.00,
            'timestamp' => '2024-03-27 10:00:00'
        ];
        $request = new Request($requestData);

        // Mock repository response
        $expectedResponse = [
            'success' => true,
            'data' => [
                'order_id' => 'MANUAL123',
                'amount' => 1000.00,
                'status' => 1
            ]
        ];
        
        $this->transactionRepository->shouldReceive('addDeposit')
            ->once()
            ->with(Mockery::type(Request::class), null)
            ->andReturn($expectedResponse);

        // Call the method
        $response = $this->depositController->storeManual($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Payment link generated successfully', $responseData->message);
        $this->assertEquals($expectedResponse['data']['order_id'], $responseData->data->order_id);
    }

    /**
     * Test callback processing without Midtrans enabled
     */
    public function test_callback_fails_when_midtrans_disabled()
    {
        // Disable Midtrans
        Config::set('midtrans.use', false);

        // Create request
        $request = new Request();

        // Call the method
        $response = $this->depositController->callback($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertStringContainsString('Midtrans is not enabled', $responseData->message);
    }

    /**
     * Test successful order ID generation
     */
    public function test_generate_order_id_success()
    {
        // Mock repository response
        $expectedOrderId = 'INV/20240327/1234';
        $this->transactionRepository->shouldReceive('generateOrderId')
            ->once()
            ->andReturn($expectedOrderId);

        // Call the method
        $response = $this->depositController->generateOrderId();
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Order ID generated successfully', $responseData->message);
        $this->assertEquals($expectedOrderId, $responseData->data->order_id);
    }

    /**
     * Test transaction status check without Midtrans
     */
    public function test_get_transaction_status_fails_when_midtrans_disabled()
    {
        // Disable Midtrans
        Config::set('midtrans.use', false);

        // Create request
        $request = new Request();
        $orderId = 'ORDER123';

        // Call the method
        $response = $this->depositController->getTransactionStatus($request, $orderId);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Failed to check transaction status', $responseData->message);
    }

    /**
     * Test mapping of transaction status
     */
    public function test_map_transaction_status()
    {
        $method = new \ReflectionMethod(DepositController::class, 'mapTransactionStatus');
        $method->setAccessible(true);

        // Test various status mappings
        $this->assertEquals('success', $method->invoke($this->depositController, 'settlement', 'bank_transfer'));
        $this->assertEquals('pending', $method->invoke($this->depositController, 'pending', 'bank_transfer'));
        $this->assertEquals('denied', $method->invoke($this->depositController, 'deny', 'bank_transfer'));
        $this->assertEquals('expired', $method->invoke($this->depositController, 'expire', 'bank_transfer'));
        $this->assertEquals('unknown', $method->invoke($this->depositController, 'invalid_status', 'bank_transfer'));

        // Test credit card specific logic
        $this->assertEquals('success', $method->invoke($this->depositController, 'capture', 'credit_card', 'accept'));
        $this->assertEquals('challenge', $method->invoke($this->depositController, 'capture', 'credit_card', 'challenge'));
    }
} 