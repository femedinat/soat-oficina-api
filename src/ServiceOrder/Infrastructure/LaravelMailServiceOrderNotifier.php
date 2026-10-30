<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Oficina\ServiceOrder\Application\CustomerSnapshot;
use Oficina\ServiceOrder\Application\ServiceOrderNotifier;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Adaptador de e-mail da OS. Falha de envio só gera log: a OS já foi gravada (evento pós-commit).
 * O e-mail leva só o número da OS e o valor; o detalhe é consultado na API (sem dados sensíveis no corpo).
 */
final readonly class LaravelMailServiceOrderNotifier implements ServiceOrderNotifier
{
    public function __construct(
        private Mailer $mailer,
        private LoggerInterface $logger,
    ) {}

    public function budgetAwaitingApproval(CustomerSnapshot $customer, string $orderId, string $total, bool $additional, DateTimeImmutable $deadline): void
    {
        $what = $additional ? 'um reparo adicional' : 'o orçamento';

        $this->send($customer, $orderId, $additional ? 'Reparo adicional aguardando aprovação' : 'Orçamento aguardando aprovação', sprintf(
            "Olá, %s!\n\nA OS %s tem %s de R$ %s aguardando a sua aprovação até %s (UTC).\nSem resposta no prazo, %s.",
            $customer->name,
            $orderId,
            $what,
            number_format((float) $total, 2, ',', '.'),
            $deadline->format('d/m/Y H:i'),
            $additional ? 'o reparo adicional será considerado recusado e a execução continua com o escopo original' : 'a OS será cancelada',
        ));
    }

    public function readyForPickup(CustomerSnapshot $customer, string $orderId): void
    {
        $this->send($customer, $orderId, 'Veículo pronto para retirada', sprintf(
            "Olá, %s!\n\nOs serviços da OS %s foram finalizados e o veículo está pronto para retirada.",
            $customer->name,
            $orderId,
        ));
    }

    public function canceled(CustomerSnapshot $customer, string $orderId, string $reason): void
    {
        $this->send($customer, $orderId, 'Ordem de serviço cancelada', sprintf(
            "Olá, %s!\n\nA OS %s foi cancelada. Motivo: %s.\nO veículo está disponível para retirada.",
            $customer->name,
            $orderId,
            $reason,
        ));
    }

    private function send(CustomerSnapshot $customer, string $orderId, string $subject, string $body): void
    {
        try {
            $this->mailer->raw($body, static function (Message $message) use ($customer, $subject): void {
                $message->to($customer->email)->subject($subject);
            });
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao enviar e-mail da OS.', [
                'service_order_id' => $orderId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
