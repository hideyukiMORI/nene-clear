<?php

declare(strict_types=1);

namespace NeneClear\User;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class SmtpInvitationMailer implements InvitationMailerInterface
{
    private readonly Mailer $mailer;

    public function __construct(
        string $smtpHost,
        int $smtpPort,
        private readonly string $fromAddress,
        private readonly string $fromName,
        string $username = '',
        #[\SensitiveParameter] string $password = '',
    ) {
        // Credentials are passed to EsmtpTransport directly (not via a DSN) so
        // they cannot leak into exception messages if the connection fails.
        $transport = new EsmtpTransport($smtpHost, $smtpPort);
        if ($username !== '') {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }
        $this->mailer = new Mailer($transport);
    }

    public function send(InvitationMailPayload $payload): void
    {
        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($payload->to)
            ->subject($payload->subject)
            ->text($payload->body);

        $this->mailer->send($email);
    }
}
