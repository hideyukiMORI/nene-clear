<?php

declare(strict_types=1);

namespace NeneClear\User;

interface UpdateUserUseCaseInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function execute(UpdateUserInput $input): User;
}
