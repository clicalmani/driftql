<?php
namespace Tonka\DriftQL\Console;

use Clicalmani\Console\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Clicalmani\Foundation\Sandbox\Sandbox;

/**
 * Create a new middleware service
 * 
 * @package Clicalmani\Console
 * @author clicalmani
 */
#[AsCommand(
    name: 'driftql:config',
    description: 'Create a new DriftQL configuration file',
    hidden: false
)]
class MakeConfig extends Command
{
    private $config_path;

    public function __construct(protected $rootPath)
    {
        $this->config_path = $rootPath . '/config';
        $this->mkdir($this->config_path);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $js_config = $this->rootPath . '/driftql.config.ts';
        $php_config = $this->config_path . '/driftql.php';
        $public_key = bin2hex(random_bytes(32));

        $success = file_put_contents(
            $js_config, 
            ltrim( 
                Sandbox::eval(file_get_contents( __DIR__ . "/samples/DriftQLConfig.sample"), ['bridge_key' => $public_key])
            )
        );
        $success = file_put_contents(
            $php_config, 
            ltrim( 
                Sandbox::eval(file_get_contents( __DIR__ . "/samples/DriftQLPHPConfig.sample"), ['bridge_key' => $public_key])
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
        $this->setHelp('Create a new DriftQL configuration file');
    }
}
