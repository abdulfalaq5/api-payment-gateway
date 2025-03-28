<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\TransactionController;
use App\Repositories\TransactionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;

class TransactionControllerTest extends TestCase
{
    protected $transactionController;
    protected $transactionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);
        $this->transactionController = new TransactionController($this->transactionRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test successful transaction history retrieval with all parameters
     */
    public function test_index_returns_transaction_history_with_all_params()
    {
        // Create request data
        $requestData = [
            'type' => 'deposit',
            'filter' => 'month',
            'per_page' => 10,
            'page' => 1
        ];
        $request = new Request($requestData);

        // Mock repository response
        $expectedResponse = [
            'success' => true,
            'data' => [
                [
                    'id' => 1,
                    'type' => 'deposit',
                    'amount' => 1000.00,
                    'transaction_date' => '2024-03-27 10:00:00',
                    'status' => 'success',
                    'description' => 'Deposit transaction'
                ]
            ],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 1,
                'last_page' => 1
            ]
        ];
        
        $this->transactionRepository->shouldReceive('getTransactionHistory')
            ->once()
            ->with('deposit', 'month', 10, 1)
            ->andReturn($expectedResponse);

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Transaction history retrieved successfully', $responseData->message);
        $this->assertEquals($expectedResponse['data'], json_decode(json_encode($responseData->data), true));
    }

    /**
     * Test successful transaction history retrieval without parameters
     */
    public function test_index_returns_transaction_history_without_params()
    {
        // Create request without parameters
        $request = new Request();

        // Mock repository response
        $expectedResponse = [
            'success' => true,
            'data' => [
                [
                    'id' => 1,
                    'type' => 'deposit',
                    'amount' => 1000.00,
                    'transaction_date' => '2024-03-27 10:00:00',
                    'status' => 'success',
                    'description' => 'Deposit transaction'
                ]
            ],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1
            ]
        ];
        
        $this->transactionRepository->shouldReceive('getTransactionHistory')
            ->once()
            ->with(null, null, null, null)
            ->andReturn($expectedResponse);

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $responseData->status);
        $this->assertEquals('Transaction history retrieved successfully', $responseData->message);
        $this->assertEquals($expectedResponse['data'], json_decode(json_encode($responseData->data), true));
    }

    /**
     * Test validation of transaction type
     */
    public function test_index_validates_transaction_type()
    {
        // Create request with invalid type
        $requestData = [
            'type' => 'invalid_type'
        ];
        $request = new Request($requestData);

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }

    /**
     * Test validation of filter parameter
     */
    public function test_index_validates_filter()
    {
        // Create request with invalid filter
        $requestData = [
            'filter' => 'invalid_filter'
        ];
        $request = new Request($requestData);

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }

    /**
     * Test validation of per_page parameter
     */
    public function test_index_validates_per_page()
    {
        // Create request with invalid per_page
        $requestData = [
            'per_page' => 101 // Exceeds maximum value
        ];
        $request = new Request($requestData);

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }

    /**
     * Test validation of page parameter
     */
    public function test_index_validates_page()
    {
        // Create request with invalid page
        $requestData = [
            'page' => 0 // Invalid: less than minimum value
        ];
        $request = new Request($requestData);

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
    }

    /**
     * Test handling of repository error
     */
    public function test_index_handles_repository_error()
    {
        // Create request data
        $requestData = [
            'type' => 'deposit',
            'filter' => 'month'
        ];
        $request = new Request($requestData);

        // Mock repository to return error
        $errorResponse = [
            'success' => false,
            'message' => 'Invalid filter parameters'
        ];
        
        $this->transactionRepository->shouldReceive('getTransactionHistory')
            ->once()
            ->with('deposit', 'month', null, null)
            ->andReturn($errorResponse);

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Invalid filter parameters', $responseData->message);
    }

    /**
     * Test handling of unexpected error
     */
    public function test_index_handles_unexpected_error()
    {
        // Create request data
        $requestData = [
            'type' => 'deposit'
        ];
        $request = new Request($requestData);

        // Mock repository to throw exception
        $this->transactionRepository->shouldReceive('getTransactionHistory')
            ->once()
            ->with('deposit', null, null, null)
            ->andThrow(new \Exception('Database error'));

        // Call the method
        $response = $this->transactionController->index($request);
        $responseData = $response->getData();

        // Assert response
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('error', $responseData->status);
        $this->assertEquals('Failed to get transaction history', $responseData->message);
    }
} 