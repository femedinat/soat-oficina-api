<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerContactNotifier;
use Oficina\Shared\Domain\ValueObject\Email;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Adaptador de infraestrutura para envio de confirmação de atualização de contato via e-mail.
 */
final readonly class LaravelMailCustomerContactNotifier implements CustomerContactNotifier
{
    public function __construct(
        private Mailer $mailer,
        private LoggerInterface $logger,
    ) {}

    public function sendContactUpdatedConfirmation(Customer $customer, ?Email $previousEmail = null): void
    {
        $body = sprintf(
            'Olá, %s! Confirmamos que os seus dados de contato na Oficina foram atualizados com sucesso. Caso não reconheça esta alteração, entre em contato imediatamente.',
            $customer->name(),
        );

        $recipients = [$customer->email()->value];

        if ($previousEmail !== null && ! $previousEmail->equals($customer->email())) {
            $recipients[] = $previousEmail->value;
        }

        try {
            $this->mailer->raw($body, static function (Message $message) use ($recipients): void {
                $message
                    ->to($recipients)
                    ->subject('Confirmação de atualização de contato');
            });
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao enviar confirmação de atualização de contato.', [
                'customer_id' => $customer->id->value,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
