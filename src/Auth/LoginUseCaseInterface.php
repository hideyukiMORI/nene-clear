<?php

declare(strict_types=1);

namespace NeneClear\Auth;

interface LoginUseCaseInterface
{
    public function execute(LoginInput $input): LoginOutput;
}
