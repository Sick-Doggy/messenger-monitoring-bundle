<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\SendInvoice;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand('demo:send', 'Wysyła fakturę przez Messenger i pokazuje raportowanie')]
final class SendInvoiceCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('invoiceId', InputArgument::OPTIONAL, 'ID faktury (kończące się FAIL = zamyka handler)', 'INV-101');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $invoiceId = (string) $input->getArgument('invoiceId');

        $this->bus->dispatch(new SendInvoice($invoiceId));

        (new SymfonyStyle($input, $output))->success(sprintf('Wysłano wiadomość SendInvoice "%s"', $invoiceId));

        return Command::SUCCESS;
    }
}
