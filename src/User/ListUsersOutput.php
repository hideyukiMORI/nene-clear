<?php

declare(strict_types=1);

namespace NeneClear\User;

final readonly class ListUsersOutput
{
    /**
     * @param list<User> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {
    }
}
