<?php
namespace Tonka\DriftQL\Console;

use Clicalmani\Console\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Clicalmani\Foundation\Sandbox\Sandbox;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Create a new middleware service
 * 
 * @package Clicalmani\Console
 * @author clicalmani
 */
#[AsCommand(
    name: 'driftql:make_contract',
    description: 'Create a new DriftQL contract class',
    hidden: false
)]
class MakeContract extends Command
{
    private $contracts_path;

    public function __construct(protected $rootPath)
    {
        $this->contracts_path = $rootPath . '/app/Contracts/DriftQL';
        $this->mkdir($this->contracts_path);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $name = $input->getArgument('name');
        $filename = $this->contracts_path . '/' . $name . '.php';

        $success = file_put_contents(
            $filename, 
            ltrim( 
                Sandbox::eval(file_get_contents( __DIR__ . "/samples/Contract.sample"), ['class' => $name])
            )
        );

        if ($success) {
            $output->writeln('Command executed successfully');
            return Command::SUCCESS;
        }

        $output->writeln('Failed to execute the command');

        return Command::FAILURE;
    }

    protected function configure() : void
    {
        $this->setHelp('Create a new DriftQL contract class');
        $this->setDefinition([
            new InputArgument('name', InputArgument::REQUIRED, 'Contract class name')
        ]);
    }
}
