<?php

namespace App\Console\Commands;

use Database\Seeders\TestSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

class SeedTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed {--test : Seed test data} {--class= : The class name of the root seeder} {--database= : The database connection to seed} {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with records';

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * Create a new database seed command instance.
     */
    public function __construct(ConnectionResolverInterface $resolver)
    {
        parent::__construct();

        $this->resolver = $resolver;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // If --test option is provided, use TestSeeder
        if ($this->option('test')) {
            $this->components->info('Seeding test data.');

            Model::unguarded(function () {
                $seeder = new TestSeeder();
                $seeder->setContainer($this->laravel);
                $seeder->setCommand($this);
                $seeder->run();
            });

            return Command::SUCCESS;
        }

        // Otherwise, use the default Laravel seed behavior
        $this->components->info('Seeding database.');

        $previousConnection = $this->resolver->getDefaultConnection();
        $this->resolver->setDefaultConnection($this->getDatabase());

        Model::unguarded(function () {
            $this->getSeeder()->__invoke();
        });

        if ($previousConnection) {
            $this->resolver->setDefaultConnection($previousConnection);
        }

        return Command::SUCCESS;
    }

    /**
     * Get a seeder instance from the container.
     *
     * @return \Illuminate\Database\Seeder
     */
    protected function getSeeder()
    {
        $class = $this->option('class') ?? 'DatabaseSeeder';

        if (! str_contains($class, '\\')) {
            $class = 'Database\\Seeders\\'.$class;
        }

        if ($class === 'Database\\Seeders\\DatabaseSeeder' &&
            ! class_exists($class)) {
            $class = 'DatabaseSeeder';
        }

        return $this->laravel->make($class)
            ->setContainer($this->laravel)
            ->setCommand($this);
    }

    /**
     * Get the name of the database connection to use.
     *
     * @return string
     */
    protected function getDatabase()
    {
        $database = $this->option('database');

        return $database ?: $this->laravel['config']['database.default'];
    }
}

