<?php

namespace AsyncPHP\Doorman\Tests\Manager;

use AsyncPHP\Doorman\Manager\ShellManager;
use AsyncPHP\Doorman\Rule\SimpleRule;
use AsyncPHP\Doorman\Task\ShellTask;
use AsyncPHP\Doorman\Tests\Test;

class ShellManagerTest extends Test
{
    /**
     * @var ShellManager
     */
    protected $manager;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->manager = new ShellManager();
    }

    /**
     * @inheritdoc
     */
    public function tearDown()
    {
        $this->manager = null;

        parent::tearDown();
    }

    /**
     * @test
     */
    public function handlesShellTasks()
    {
        $task1 = new ShellTask(function () {
            touch(__DIR__ . "/task1.tmp");
        });

        $task2 = new ShellTask(function () {
            touch(__DIR__ . "/task2.tmp");
        });

        $task3 = new ShellTask(function () {
            touch(__DIR__ . "/task3.tmp");
        });

        $this->manager->addTask($task1);
        $this->manager->addTask($task2);
        $this->manager->addTask($task3);

        @unlink(__DIR__ . "/task1.tmp");
        @unlink(__DIR__ . "/task2.tmp");
        @unlink(__DIR__ . "/task3.tmp");

        while ($this->manager->tick()) {
            usleep(250);
        }

        $this->assertFileExists(__DIR__ . "/task1.tmp");
        $this->assertFileExists(__DIR__ . "/task2.tmp");
        $this->assertFileExists(__DIR__ . "/task3.tmp");

        @unlink(__DIR__ . "/task1.tmp");
        @unlink(__DIR__ . "/task2.tmp");
        @unlink(__DIR__ . "/task3.tmp");
    }

    /**
     * @test
     */
    public function handlesRules()
    {
        // Let's create a task that keeps running for 10 seconds.

        $task1 = new ShellTask(function () {
            $ticks = 0;

            while ($ticks++ < 10) {
                sleep(1);
            }
        });

        $this->manager->addTask($task1);

        // Then let's make a rule that says only 1 process can be run at a time.

        $rule1 = new SimpleRule();
        $rule1->setProcesses(1);

        $this->manager->addRule($rule1);

        $ticks = 0;
        $added = false;

        @unlink(__DIR__ . "/fail-handles-rules.tmp");

        // We'll keep trying to add a task, but it shouldn't be run
        // as there is already 1 task running in the background...

        while ($this->manager->tick() && $ticks++ < 3) {
            sleep(1);

            if (!$added && $ticks > 1) {
                $task2 = new ShellTask(function () {
                    touch(__DIR__ . "/fail-handles-rules.tmp");
                });

                $this->manager->addTask($task2);
            }

            $this->assertFileNotExists(__DIR__ . "/fail-handles-rules.tmp");
        }

        @unlink(__DIR__ . "/fail-handles-rules.tmp");
    }
}
