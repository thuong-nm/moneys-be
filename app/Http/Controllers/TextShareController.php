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
        $isAuthenticated = $request->user() !== null;

        // Validation rules depend on authentication status
        $rules = [
            'content' => 'required|string|max:5000000', // ~5MB compressed
            'format' => 'nullable|string|in:json,xml,html,markdown,plain,base64',
            'password' => 'nullable|string|min:1|max:255',
            'browser_id' => 'nullable|string|max:64',
        ];

        // Only require expires_in for guest users (not logged in)
        if (!$isAuthenticated) {
            $rules['expires_in'] = 'required|string|in:1day,1week,1month,1year';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Determine expires_at based on authentication status
        $expiresAt = null; // Default for logged-in users (permanent)

        if (!$isAuthenticated && isset($validated['expires_in'])) {
            // Guest users: set expiration based on their choice
            $expiresAt = match ($validated['expires_in']) {
                '1day' => now()->addDay(),
                '1week' => now()->addWeek(),
                '1month' => now()->addMonth(),
                '1year' => now()->addYear(),
            };
        }

        $textShare = TextShare::create([
            'hash_id' => TextShare::generateHashId(),
            'user_id' => $isAuthenticated ? $request->user()->id : null,
            'browser_id' => $validated['browser_id'] ?? null,
            'content' => $validated['content'],
            'format' => $validated['format'] ?? 'plain',
            'password' => isset($validated['password']) ? password_hash($validated['password'], PASSWORD_DEFAULT) : null,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'hash_id' => $textShare->hash_id,
            'url' => url("/s/{$textShare->hash_id}"),
            'expires_at' => $textShare->expires_at?->toIso8601String(),
            'is_permanent' => $textShare->expires_at === null,
        ], 201);
    }

    /**
     * Show a text share by hash ID
     */
    public function show(string $hashId, Request $request): JsonResponse|View
    {
        $textShare = TextShare::notExpired()
            ->where('hash_id', $hashId)
            ->first();

        if (! $textShare) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Text share not found or expired',
                ], 404);
            }

            abort(404, 'Text share not found or expired');
        }

        // Check if password protected
        if ($textShare->isPasswordProtected()) {
            // Check session or cookie for already verified password
            $sessionKey = "text_share_verified_{$hashId}";
            $cookieKey = "ts_v_{$hashId}";

            if (!session($sessionKey) && !$request->cookie($cookieKey)) {
                // Password required
                if ($request->wantsJson()) {
                    $providedPassword = $request->input('password');

                    if (!$providedPassword) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Password required',
                            'password_required' => true,
                        ], 401);
                    }

                    if (!$textShare->verifyPassword($providedPassword)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid password',
                            'password_required' => true,
                        ], 401);
                    }

                    // Password correct - set session
                    session([$sessionKey => true]);
                } else {
                    // For web view, show password prompt
                    return view('text-share.show', [
                        'textShare' => $textShare,
                        'requirePassword' => true,
                    ]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'content' => $textShare->content,
                'format' => $textShare->format,
                'expires_at' => $textShare->expires_at->toIso8601String(),
            ]);
        }

        return view('text-share.show', [
            'textShare' => $textShare,
            'requirePassword' => false,
        ]);
    }

    /**
     * Verify password for a text share
     */
    public function verifyPassword(string $hashId, Request $request): JsonResponse
    {
        $textShare = TextShare::notExpired()
            ->where('hash_id', $hashId)
            ->first();

        if (!$textShare) {
            return response()->json([
                'success' => false,
                'message' => 'Text share not found or expired',
            ], 404);
        }

        $password = $request->input('password');

        if (!$password || !$textShare->verifyPassword($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        // Set session to remember password verification
        $sessionKey = "text_share_verified_{$hashId}";
        session([$sessionKey => true]);

        // Also set a persistent cookie that lasts 30 days
        $cookieKey = "ts_v_{$hashId}";
        $cookie = cookie($cookieKey, '1', 43200); // 30 days in minutes

        return response()->json([
            'success' => true,
            'message' => 'Password verified',
        ])->cookie($cookie);
    }

    /**
     * Get history of text shares
     * - For logged-in users: returns all their shares (user_id based)
     * - For guests: returns shares by browser_id
     */
    public function history(Request $request): JsonResponse
    {
        $isAuthenticated = $request->user() !== null;

        if ($isAuthenticated) {
            // Logged-in user: fetch ALL their shares (no expiration filter for user shares)
            $shares = TextShare::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(100) // Higher limit for logged-in users
                ->get(['hash_id', 'format', 'created_at', 'expires_at'])
                ->map(function ($share) {
                    return [
                        'hash_id' => $share->hash_id,
                        'url' => url("/s/{$share->hash_id}"),
                        'format' => $share->format,
                        'created_at' => $share->created_at->toIso8601String(),
                        'expires_at' => $share->expires_at?->toIso8601String(),
                        'is_password_protected' => $share->isPasswordProtected(),
                        'is_permanent' => $share->expires_at === null,
                    ];
                });
        } else {
            // Guest user: require browser_id and fetch only non-expired shares
            $browserId = $request->input('browser_id');

            if (!$browserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Browser ID is required',
                ], 400);
            }

            $shares = TextShare::notExpired()
                ->byBrowser($browserId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get(['hash_id', 'format', 'created_at', 'expires_at'])
                ->map(function ($share) {
                    return [
                        'hash_id' => $share->hash_id,
                        'url' => url("/s/{$share->hash_id}"),
                        'format' => $share->format,
                        'created_at' => $share->created_at->toIso8601String(),
                        'expires_at' => $share->expires_at->toIso8601String(),
                        'is_password_protected' => $share->isPasswordProtected(),
                        'is_permanent' => false,
                    ];
                });
        }

        return response()->json([
            'success' => true,
            'shares' => $shares,
        ]);
    }
}
