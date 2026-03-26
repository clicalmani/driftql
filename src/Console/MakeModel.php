<?php
namespace Tonka\DriftQL\Console;

use Clicalmani\Console\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Clicalmani\Foundation\Sandbox\Sandbox;

/**
 * Create a new middleware service
 * 
 * @package Clicalmani\Console
 * @author clicalmani
 */
#[AsCommand(
    name: 'driftql:model',
    description: 'Create a new DriftQL model',
    hidden: false
)]
class MakeModel extends Command
{
    private $models_path;

    public function __construct(protected $rootPath)
    {
        $this->models_path = $this->rootPath . '/resources/js/models';
        $this->mkdir($this->models_path);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $name = $input->getArgument('name');

        $filename = $this->models_path . '/' . $name . '.ts';

        $success = file_put_contents(
            $filename, 
            ltrim( 
                Sandbox::eval(file_get_contents( __DIR__ . "/Samples/DriftQLModel.sample"), [
                    'model' => $name
                ])
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
        $this->setHelp('Create a new DriftQL model');
        $this->setDefinition([
            new InputArgument('name', InputArgument::REQUIRED, 'Model name')
        ]);
    }
}
