<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
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
        string $password = '',
    ) {
        $userInfo = $username !== '' ? sprintf('%s:%s@', urlencode($username), urlencode($password)) : '';
        $dsn = sprintf('smtp://%s%s:%d', $userInfo, $smtpHost, $smtpPort);
        $this->mailer = new Mailer(Transport::fromDsn($dsn));
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
