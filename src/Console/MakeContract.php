<?php
namespace Tonka\DriftQL\Console;

use Clicalmani\Console\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Clicalmani\Foundation\Sandbox\Sandbox;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Console command for creating new DriftQL contract classes.
 * 
 * @package Tonka\DriftQL\Console
 * @author clicalmani
 */
#[AsCommand(
    name: 'driftql:make_contract',
    description: 'Create a new DriftQL contract class',
    hidden: false
)]
class MakeContract extends Command
{
    /**
     * Directory path where generated contract files are stored.
     * 
     * @var string
     */
    private string $contracts_path;

    /**
     * MakeContract command constructor.
     *
     * @param string $rootPath Application root path.
     */
    public function __construct(protected string $rootPath)
    {
        $this->contracts_path = $rootPath . '/app/Contracts/DriftQL';
        $this->mkdir($this->contracts_path);
        parent::__construct();
    }

    /**
     * Execute the contract generation command.
     *
     * @param InputInterface $input Command input interface.
     * @param OutputInterface $output Command output interface.
     * @return int Command execution status code.
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $name     = $input->getArgument('name');
        $filename = $this->contracts_path . '/' . $name . '.php';

        $success = file_put_contents(
            $filename, 
            ltrim( 
                Sandbox::eval(file_get_contents(__DIR__ . "/samples/Contract.sample"), ['class' => $name])
            )
        );

        if (false !== $success) {
            $output->writeln('Command executed successfully');
            return Command::SUCCESS;
        }

        $output->writeln('Failed to execute the command');

        return Command::FAILURE;
    }

    /**
     * Configure command arguments and help details.
     * 
     * @return void
     */
    protected function configure() : void
    {
        $this->setHelp('Create a new DriftQL contract class');
        $this->setDefinition([
            new InputArgument('name', InputArgument::REQUIRED, 'Contract class name')
        ]);
    }
}