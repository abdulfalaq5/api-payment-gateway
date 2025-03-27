<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="Laravel Auth API",
 *         description="Laravel Auth API documentation with Swagger",
 *         @OA\Contact(
 *             email="admin@example.com"
 *         )
 *     ),
 *     @OA\Server(
 *         description="Laravel Auth API",
 *         url=L5_SWAGGER_CONST_HOST
 *     )
 * )
 * @OA\Components(
 *     @OA\SecurityScheme(
 *         securityScheme="bearerAuth",
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="JWT"
 *     )
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
