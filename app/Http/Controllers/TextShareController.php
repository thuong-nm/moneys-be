<?php

namespace App\Http\Controllers;

use App\Models\TextShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class TextShareController extends Controller
{
    /**
     * Store a new text share
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000000', // ~5MB compressed
            'format' => 'nullable|string|in:json,xml,html,markdown,plain,base64',
            'expires_in' => 'required|string|in:1day,1week,1month,1year',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $expiresAt = match ($validated['expires_in']) {
            '1day' => now()->addDay(),
            '1week' => now()->addWeek(),
            '1month' => now()->addMonth(),
            '1year' => now()->addYear(),
        };

        $textShare = TextShare::create([
            'hash_id' => TextShare::generateHashId(),
            'content' => $validated['content'],
            'format' => $validated['format'] ?? 'plain',
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'hash_id' => $textShare->hash_id,
            'url' => url("/s/{$textShare->hash_id}"),
            'expires_at' => $textShare->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Show a text share by hash ID
     */
    public function show(string $hashId): JsonResponse|View
    {
        $textShare = TextShare::notExpired()
            ->where('hash_id', $hashId)
            ->first();

        if (! $textShare) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Text share not found or expired',
                ], 404);
            }

            abort(404, 'Text share not found or expired');
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'content' => $textShare->content,
                'format' => $textShare->format,
                'expires_at' => $textShare->expires_at->toIso8601String(),
            ]);
        }

        return view('text-share.show', ['textShare' => $textShare]);
    }
}
