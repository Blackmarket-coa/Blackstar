<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Bearer tokens for node operators.
 *
 * Until this existed every non-webhook route required `auth` against a
 * session guard with no login route behind it, so no real operator could
 * call the API. Contract (a client is written against it):
 *
 *   POST /api/auth/token         {email, password, device_name?}
 *                                → 200 {token, token_type: "Bearer"}
 *                                → 422 generic message on bad credentials
 *   POST /api/auth/token/revoke  (authenticated) revokes the calling token
 */
class AuthTokenController extends Controller
{
    /**
     * Checked against when the email matches no user, so an unknown email
     * costs the same bcrypt verification as a wrong password and response
     * timing does not reveal which accounts exist. Hash of random bytes
     * nobody holds; it can never verify.
     */
    private const TIMING_DUMMY_HASH = '$2y$10$Djb2oBlLXm/HN2gb8j0zE.rxs.M9QVxuLVvv7BVkysYTdaQAAzzrG';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        $hash = $user?->password ?: self::TIMING_DUMMY_HASH;

        if (!Hash::check($data['password'], $hash) || !$user) {
            // One message for unknown email and wrong password alike: no
            // user enumeration.
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'api');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        // A session-authenticated caller carries a TransientToken, which has
        // nothing to revoke; only a real personal access token is deleted.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
