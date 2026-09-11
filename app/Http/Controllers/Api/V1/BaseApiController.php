<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    /**
     * Return a successful JSON response.
     */
    protected function apiResponse(mixed $data = null, string $message = ''): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== '') {
            $response['message'] = $message;
        }

        return response()->json($response);
    }

    /**
     * Return a 201 created JSON response with the new resource id.
     */
    protected function apiCreated(int $id, string $message = 'Recurso creado correctamente'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['id' => $id],
            'message' => $message,
        ], 201);
    }

    /**
     * Return an error JSON response.
     */
    protected function apiError(string $message, int $code = 422, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Validate request data, throwing for JSON consumers.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    protected function validateRequest(Request $request, array $rules, array $messages = []): array
    {
        return $request->validate($rules, $messages);
    }
}
