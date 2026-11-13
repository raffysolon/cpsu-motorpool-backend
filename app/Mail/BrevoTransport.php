<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'brevo+https';
    }

    protected function doSend(SentMessage $message): void
    {
        if (trim($this->apiKey) === '') {
            throw new TransportException('BREVO_API_KEY is missing.');
        }

        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || $email->getAttachments() !== []) {
            throw new TransportException('Brevo transport requires an email without attachments.');
        }

        $address = static fn (Address $value): array => array_filter([
            'email' => $value->getAddress(),
            'name' => $value->getName(),
        ], static fn ($value) => $value !== '');

        $payload = [
            'sender' => $address($email->getFrom()[0]),
            'subject' => $email->getSubject(),
        ];
        foreach (['to' => $email->getTo(), 'cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $addresses) {
            if ($addresses !== []) {
                $payload[$field] = array_map($address, $addresses);
            }
        }
        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = $email->getTextBody();
        }

        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey])
                ->acceptJson()->connectTimeout(5)->timeout(15)
                ->post('https://api.brevo.com/v3/smtp/email', $payload);
        } catch (ConnectionException) {
            // Do not expose the API key, recipient, or reset link in logs.
            throw new TransportException('Brevo HTTPS connection failed or timed out.');
        }

        if (! $response->successful()) {
            throw new TransportException('Brevo rejected email (HTTP '.$response->status().'). Check API permissions and verified sender.');
        }
        if (! is_string($response->json('messageId')) || $response->json('messageId') === '') {
            throw new TransportException('Brevo returned no email message ID.');
        }
        $message->setMessageId($response->json('messageId'));
    }
}
