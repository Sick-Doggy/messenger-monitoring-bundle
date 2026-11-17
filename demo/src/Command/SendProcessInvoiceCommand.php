<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ProcessInvoice;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand('demo:process', 'Wysyła mutowalną wiadomość ProcessInvoice (patrz: payload na stanach)')]
final class SendProcessInvoiceCommand extends \Symfony\Component\Console\Command\Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('orderId', InputArgument::OPTIONAL, 'ID zamówienia', 'ORD-1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orderId = (string) $input->getArgument('orderId');

        $this->bus->dispatch(new ProcessInvoice($orderId));

        (new SymfonyStyle($input, $output))->success(sprintf('Wysłano wiadomość ProcessInvoice "%s"', $orderId));

        return Command::SUCCESS;
    }
}
