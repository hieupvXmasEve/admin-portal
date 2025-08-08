<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Responses;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Response;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_response_structure(): void
    {
        $data = ['key' => 'value'];
        $message = 'Operation successful';

        $response = ApiResponse::success($data, $message);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertTrue($content['success']);
        $this->assertEquals($message, $content['message']);
        $this->assertEquals($data, $content['data']);
        $this->assertArrayHasKey('timestamp', $content);
    }

    public function test_success_response_without_data(): void
    {
        $response = ApiResponse::success();

        $content = json_decode($response->getContent(), true);

        $this->assertTrue($content['success']);
        $this->assertEquals('Operation successful', $content['message']);
        $this->assertArrayNotHasKey('data', $content);
    }

    public function test_error_response_structure(): void
    {
        $message = 'Something went wrong';
        $errors = ['field' => ['error message']];
        $errorCode = 'TEST_ERROR';

        $response = ApiResponse::error($message, $errors, $errorCode);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals($message, $content['message']);
        $this->assertEquals($errors, $content['errors']);
        $this->assertEquals($errorCode, $content['error_code']);
        $this->assertArrayHasKey('timestamp', $content);
    }

    public function test_validation_error_response(): void
    {
        $errors = ['email' => ['The email field is required.']];

        $response = ApiResponse::validationError($errors);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals('Validation failed', $content['message']);
        $this->assertEquals($errors, $content['errors']);
        $this->assertEquals('VALIDATION_ERROR', $content['error_code']);
    }

    public function test_authentication_error_response(): void
    {
        $response = ApiResponse::authenticationError();

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals('Authentication failed', $content['message']);
        $this->assertEquals('AUTHENTICATION_ERROR', $content['error_code']);
    }

    public function test_authorization_error_response(): void
    {
        $response = ApiResponse::authorizationError();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals('Access denied', $content['message']);
        $this->assertEquals('AUTHORIZATION_ERROR', $content['error_code']);
    }

    public function test_not_found_response(): void
    {
        $response = ApiResponse::notFound();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals('Resource not found', $content['message']);
        $this->assertEquals('NOT_FOUND', $content['error_code']);
    }

    public function test_rate_limit_error_response(): void
    {
        $response = ApiResponse::rateLimitError();

        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals('Too many requests', $content['message']);
        $this->assertEquals('RATE_LIMIT_ERROR', $content['error_code']);
    }

    public function test_business_logic_error_response(): void
    {
        $message = 'Business rule violation';
        $errors = ['rule' => 'Cannot perform this action'];

        $response = ApiResponse::businessLogicError($message, $errors);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals($message, $content['message']);
        $this->assertEquals($errors, $content['errors']);
        $this->assertEquals('BUSINESS_LOGIC_ERROR', $content['error_code']);
    }

    public function test_server_error_response(): void
    {
        $response = ApiResponse::serverError();

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals('Internal server error', $content['message']);
        $this->assertEquals('SERVER_ERROR', $content['error_code']);
    }
}
