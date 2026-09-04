<?php
namespace Tonka\DriftQL\Console;

use Clicalmani\Console\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Clicalmani\Foundation\Sandbox\Sandbox;

/**
 * Console command for creating new DriftQL TypeScript models.
 * 
 * @package Tonka\DriftQL\Console
 * @author clicalmani
 */
#[AsCommand(
    name: 'driftql:model',
    description: 'Create a new DriftQL model',
    hidden: false
)]
class MakeModel extends Command
{
    /**
     * Directory path where generated TypeScript model files are stored.
     * 
     * @var string
     */
    private string $models_path;

    /**
     * MakeModel command constructor.
     *
     * @param string $rootPath Application root path.
     */
    public function __construct(protected string $rootPath)
    {
        $this->models_path = $this->rootPath . '/resources/js/database';
        $this->mkdir($this->models_path);
        parent::__construct();
    }

    /**
     * Execute the DriftQL model generation command.
     *
     * @param InputInterface $input Command input interface.
     * @param OutputInterface $output Command output interface.
     * @return int Command execution status code.
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $name = $input->getArgument('name');

        $filename = $this->models_path . '/' . $name . '.ts';

        $success = file_put_contents(
            $filename, 
            ltrim( 
                Sandbox::eval(file_get_contents(__DIR__ . "/samples/DriftQLModel.sample"), [
                    'model' => $name 
                ])
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
     * Configure command arguments and help information.
     * 
     * @return void
     */
    protected function configure() : void
    {
        $this->setHelp('Create a new DriftQL model');
        $this->setDefinition([
            new InputArgument('name', InputArgument::REQUIRED, 'Model name')
        ]);
    }
}