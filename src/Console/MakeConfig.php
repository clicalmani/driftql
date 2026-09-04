<?php
namespace Tonka\DriftQL\Console;

use Clicalmani\Console\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Clicalmani\Foundation\Sandbox\Sandbox;

/**
 * Console command for generating DriftQL configuration files.
 * 
 * @package Tonka\DriftQL\Console
 * @author clicalmani
 */
#[AsCommand(
    name: 'driftql:config',
    description: 'Create a new DriftQL configuration file',
    hidden: false
)]
class MakeConfig extends Command
{
    /**
     * Path to the target application config directory.
     * 
     * @var string
     */
    private string $config_path;

    /**
     * MakeConfig command constructor.
     *
     * @param string $rootPath Application root path.
     */
    public function __construct(protected string $rootPath)
    {
        $this->config_path = $rootPath . '/config';
        $this->mkdir($this->config_path);
        parent::__construct();
    }

    /**
     * Execute the configuration generation command.
     *
     * @param InputInterface $input Command input interface.
     * @param OutputInterface $output Command output interface.
     * @return int Command execution status code.
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $js_config  = $this->rootPath . '/driftql.config.ts';
        $php_config = $this->config_path . '/driftql.php';
        $public_key = bin2hex(random_bytes(32));

        $js_success = file_put_contents(
            $js_config, 
            ltrim( 
                Sandbox::eval(file_get_contents(__DIR__ . "/samples/DriftQLConfig.sample"), ['bridge_key' => $public_key])
            )
        );

        $php_success = file_put_contents(
            $php_config, 
            ltrim( 
                Sandbox::eval(file_get_contents(__DIR__ . "/samples/DriftQLPHPConfig.sample"), ['bridge_key' => $public_key])
            )
        );

        if (false !== $js_success && false !== $php_success) {
            $output->writeln('Command executed successfully');
            return Command::SUCCESS;
        }

        $output->writeln('Failed to execute the command');

        return Command::FAILURE;
    }

    /**
     * Configure command help information.
     * 
     * @return void
     */
    protected function configure() : void
    {
        $this->setHelp('Create a new DriftQL configuration file');
    }
}