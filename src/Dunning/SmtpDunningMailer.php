<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class SmtpDunningMailer implements DunningMailerInterface
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
        // Credentials are passed directly to EsmtpTransport instead of embedding
        // them in a DSN string, which prevents accidental credential exposure in
        // exception messages or logs if the SMTP connection fails.
        $transport = new EsmtpTransport($smtpHost, $smtpPort);
        if ($username !== '') {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }
        $this->mailer = new Mailer($transport);
    }

    public function channel(): string
    {
        return 'email';
    }

    public function send(DunningMailPayload $payload): void
    {
        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($payload->to)
            ->subject($payload->subject)
            ->text($payload->body);

        $this->mailer->send($email);
    }
}
