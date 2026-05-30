<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LoginHandler
{
    public function __construct(
        private LoginUseCaseInterface $login,
        private JsonResponseFactory $response,
        private ?LoginThrottleInterface $throttle = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $errors = [];
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '') {
            $errors[] = new ValidationError('email', 'Email is required.', 'required');
        }

        if ($password === '') {
            $errors[] = new ValidationError('password', 'Password is required.', 'required');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Brute-force / credential-stuffing guard keyed on email + client IP.
        $identifier = strtolower($email) . '|' . $this->clientIp($request);

        if ($this->throttle !== null && $this->throttle->secondsUntilUnlocked($identifier) > 0) {
            throw new TooManyLoginAttemptsException($this->throttle->secondsUntilUnlocked($identifier));
        }

        try {
            $output = $this->login->execute(new LoginInput(email: $email, password: $password));
        } catch (InvalidCredentialsException $e) {
            $this->throttle?->recordFailure($identifier);
            throw $e;
        }

        $this->throttle?->clear($identifier);

        return $this->response->create([
            'token' => $output->token,
            'user' => [
                'user_id' => $output->userId,
                'email' => $output->email,
                'role' => $output->role,
                'organization_id' => $output->organizationId,
            ],
        ]);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();

        return (string) ($params['REMOTE_ADDR'] ?? 'unknown');
    }
}
