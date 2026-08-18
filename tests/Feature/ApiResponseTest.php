<?php

namespace Tests\Feature;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Verify the shared response envelope for resources and API exceptions.
 */
class ApiResponseTest extends TestCase
{
    /**
     * Register isolated API routes used to verify response contracts.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/_test/response/resource', fn () => ApiResponse::resource(
            new ResponseProbeResource(['id' => 1, 'name' => 'Alpha']),
            'Resource retrieved',
        ));

        Route::get('/api/_test/response/collection', fn () => ApiResponse::collection(
            ResponseProbeResource::collection([
                ['id' => 1, 'name' => 'Alpha'],
                ['id' => 2, 'name' => 'Beta'],
            ]),
            'Resources retrieved',
        ));

        Route::get('/api/_test/response/paginated', function () {
            $paginator = new LengthAwarePaginator(
                [
                    ['id' => 1, 'name' => 'Alpha'],
                    ['id' => 2, 'name' => 'Beta'],
                ],
                12,
                2,
                1,
                ['path' => url('/api/_test/response/paginated')],
            );

            return ApiResponse::paginated(
                ResponseProbeResource::collection($paginator),
                'Resources retrieved',
            );
        });

        Route::get('/api/_test/response/failure', function (): never {
            throw new RuntimeException('Probe failure');
        });

        Route::get('/web-response-probe', fn () => response('<h1>Web response</h1>'));
    }

    public function test_single_resource_uses_the_standard_success_envelope(): void
    {
        $this->getJson('/api/_test/response/resource')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Resource retrieved',
                'data' => ['id' => 1, 'name' => 'Alpha'],
            ])
            ->assertJsonMissingPath('data.data');
    }

    public function test_non_paginated_collection_uses_the_standard_success_envelope(): void
    {
        $this->getJson('/api/_test/response/collection')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('meta');
    }

    public function test_paginated_collection_exposes_meta_without_laravel_links(): void
    {
        $this->getJson('/api/_test/response/paginated')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Resources retrieved',
                'meta' => [
                    'current_page' => 1,
                    'from' => 1,
                    'last_page' => 6,
                    'per_page' => 2,
                    'to' => 2,
                    'total' => 12,
                ],
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('links')
            ->assertJsonMissingPath('data.data');
    }

    public function test_missing_api_route_returns_json_not_html(): void
    {
        $this->get('/api/_test/response/missing')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }

    public function test_invalid_api_method_returns_json_not_html(): void
    {
        $this->post('/api/_test/response/resource')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'success' => false,
                'message' => 'Method not allowed.',
                'errors' => [],
            ]);
    }

    public function test_unexpected_api_exception_returns_json_not_html(): void
    {
        $this->get('/api/_test/response/failure')
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'success' => false,
                'message' => 'Probe failure',
                'errors' => [],
            ]);
    }

    public function test_web_route_keeps_its_html_response(): void
    {
        $this->get('/web-response-probe')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('<h1>Web response</h1>', false);
    }
}

/**
 * Transform test records without coupling response tests to domain models.
 */
class ResponseProbeResource extends JsonResource
{
    /**
     * Convert the probe record into its public representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
        ];
    }
}
