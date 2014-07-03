<?php

namespace Cake\Migrations\Test\Command;

use Cake\Migrations\Command\Create;
use Cake\TestSuite\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the create command
 *
 */
class CreateTest extends TestCase {

	public function testConfigure() {
		$command = new Create();
		$tester = new CommandTester($command);
		$this->assertEquals('create', $command->getName());
		$tester->execute(['name' => 'Foo']);
	}

}
