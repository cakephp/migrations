<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Console\CommandCollection;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Migrations\Test\Command\CustomBakeMigrationDiffCommand;

class BakeApplication extends BaseApplication
{
    public function middleware(MiddlewareQueue $middleware): MiddlewareQueue
    {
        return $middleware;
    }

    public function routes(RouteBuilder $routes): void
    {
    }

    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        $commands->add('custom bake migration_diff', CustomBakeMigrationDiffCommand::class);
        return $commands;
    }

    public function bootstrap(): void
    {
        $this->addPlugin('Migrations');
        $this->addPlugin('Bake', ['boostrap' => true]);
    }
}
