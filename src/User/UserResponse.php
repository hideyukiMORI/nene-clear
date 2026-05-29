<?php

declare(strict_types=1);

namespace NeneClear\User;

/**
 * Maps a {@see User} to its public JSON shape (terminology.md / openapi.yaml).
 */
final readonly class UserResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $user): array
    {
        return [
            'user_id' => $user->id,
            'organization_id' => $user->organizationId,
            'email' => $user->email,
            'role' => $user->role->value,
            'status' => $user->status->value,
        ];
    }
}
