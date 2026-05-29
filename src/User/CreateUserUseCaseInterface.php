<?php

declare(strict_types=1);

namespace NeneClear\User;

interface CreateUserUseCaseInterface
{
    /**
     * @throws UserAlreadyExistsException
     */
    public function execute(CreateUserInput $input): User;
}
