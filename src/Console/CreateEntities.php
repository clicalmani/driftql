<?php
namespace Tonka\DriftQL\Console;

use Clicalmani\Console\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Clicalmani\Foundation\Sandbox\Sandbox;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Console command for generating default DriftQL RBAC entities and models.
 * 
 * @package Tonka\DriftQL\Console
 * @author clicalmani
 */
#[AsCommand(
    name: 'driftql:create_entities',
    description: 'Migrate DriftQL database tables',
    hidden: false
)]
class CreateEntities extends Command
{
    /**
     * Path to the target database entities directory.
     * 
     * @var string
     */
    private string $entities_path;

    /**
     * Path to the target application models directory.
     * 
     * @var string
     */
    private string $models_path;

    /**
     * Create Entities command constructor.
     *
     * @param string $rootPath Application root path.
     */
    public function __construct(protected string $rootPath)
    {
        $this->entities_path = $rootPath . '/database/entities';
        $this->models_path   = $rootPath . '/app/Models';
        
        $this->mkdir($this->entities_path);
        $this->mkdir($this->models_path);
        
        parent::__construct();
    }

    /**
     * Execute the entity generation command.
     *
     * @param InputInterface $input Command input interface.
     * @param OutputInterface $output Command output interface.
     * @return int Command execution result code.
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $tables = [
            [
                'model' => 'Role',
                'name'  => 'roles',
                'keys'  => ['id']
            ],
            [
                'model' => 'Permission',
                'name'  => 'permissions',
                'keys'  => ['id']
            ],
            [
                'model' => 'PermissionRole',
                'name'  => 'permission_role',
                'keys'  => ['role_id', 'permission_id']
            ],
            [
                'model' => 'UserRole',
                'name'  => 'users_roles',
                'keys'  => ['user_id', 'role_id']
            ],
        ];

        foreach ($tables as $table) {
            $db_entity = new ArrayInput([
                'command' => 'make:entity',
                'model'   => $table['model'],
                'table'   => $table['name'],
                'keys'    => $table['keys'],
                '--seed'  => null
            ]);

            if (0 !== $this->getApplication()->doRun($db_entity, $output)) {
                $output->writeln("Could not create the database entity model " . $table['model']);
                return Command::FAILURE;
            }

            file_put_contents(
                $this->entities_path . '/' . $table['model'] . 'Entity.php', 
                ltrim( 
                    Sandbox::eval(file_get_contents( __DIR__ . "/samples/{$table['model']}Entity.sample"))
                )
            );

            file_put_contents(
                $this->models_path . '/' . $table['model'] . '.php', 
                ltrim( 
                    Sandbox::eval(file_get_contents( __DIR__ . "/samples/{$table['model']}.sample"))
                )
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Configure command options and help information.
     * 
     * @return void
     */
    protected function configure() : void
    {
        $this->setHelp('Migrate DriftQL database tables');
    }
}