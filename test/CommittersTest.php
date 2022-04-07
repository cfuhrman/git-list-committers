<?php

/**
 * CommittersTest.php
 *
 * Copyright (c) 2022 Christopher M. Fuhrman
 * All rights reserved
 *
 * This program is free software; you can redistribute it and/or
 * modify it under terms of the Simplified BSD License (also
 * known as the "2-Clause License" or "FreeBSD License".)
 *
 * Created on Fri Apr  1 12:17:00 2022 PDT
 */

use Git\Committers as Committers;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @group Git
 */
final class CommittersTest extends TestCase
{
    const BRANCH_MASTER = 'master';
    const BRANCH_LIVE   = 'live';
    const SCRATCH_FILE  = 'lincoln.txt';

    /**
     * @var string $sampleRepoLocation
     * @static
     */
    private static $sampleRepoLocation;

    /**
     * @var string $executionDirectory
     */
    protected $executionDirectory;


    /**
     * @return void
     * @static
     */
    public static function setUpBeforeClass(): void
    {
        $cwd                      = getcwd();
        self::$sampleRepoLocation = tempnam(sys_get_temp_dir(), 'CMF');

        // Since tempnam() will create a temporary file, we need to delete it since we wish to have a directory
        if (file_exists(self::$sampleRepoLocation)) {
            unlink(self::$sampleRepoLocation);
        }

        mkdir(self::$sampleRepoLocation);

        exec("git init " . self::$sampleRepoLocation);

        chdir(self::$sampleRepoLocation);
        exec("echo 'Four score and seven years ago.' > " . self::SCRATCH_FILE);

        exec("git add " . self::SCRATCH_FILE);
        exec("git commit -m 'First Commit' " . self::SCRATCH_FILE);

        exec("echo '  Our fathers brought forth on this continent, a new nation, conceived in Liberty, and dedicated to the proposition that all men are created equal.' >> " . self::SCRATCH_FILE);

        exec("git commit -m 'Second Commit' " . self::SCRATCH_FILE);

        // Create a 2nd branch
        exec("git checkout -b " . self::BRANCH_LIVE . " >/dev/null 2>&1");

        // Move back to first branch
        exec("git checkout " . self::BRANCH_MASTER . " >/dev/null 2>&1");
        chdir($cwd);
    }

    /**
     * @return void
     * @static
     */
    public static function tearDownAfterClass(): void
    {
        exec("rm -rf " . self::$sampleRepoLocation);
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->executionDirectory = getcwd();

        chdir(self::$sampleRepoLocation);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        chdir($this->executionDirectory);
    }

    /**
     * Tests git commands
     *
     * @return void
     */
    public function testGitCommand()
    {
        $committers = new Committers();

        $this->assertEquals("/usr/bin/git", $committers->getGitCommand());

        $committers->withGitCommand("/bin/cp");

        $this->assertEquals("/bin/cp", $committers->getGitCommand());

        // Test bogus git command
        $this->expectException(Exception::class);

        $committers->withGitCommand("/path/to/completely/bogus/command");
    }

    /**
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testSourceBranch()
    {
        $committers = new Committers();

        $committers->withSourceBranch(self::BRANCH_MASTER);

        $this->assertEquals(self::BRANCH_MASTER, $committers->getSourceBranch());
    }

    /**
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testTargetBranch()
    {
        $committers = new Committers();

        $committers->withTargetBranch('live');

        $this->assertEquals('live', $committers->getTargetBranch());
    }

    /**
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws OverflowException
     */
    public function testRun()
    {
        // Switch to new branch
        exec("git checkout new-branch >/dev/null 2>&1");

        $this->assertTrue(is_file(self::SCRATCH_FILE));

        exec("echo '  Now we are engaged in a great civil war, testing whether that nation, or any nation so conceived, and so dedicated, can long endure.' >> " . self::SCRATCH_FILE);

        exec("git commit -m 'Branch commit' " . self::SCRATCH_FILE);

        $committers = new Committers();

        $committers->withGitRepositoryPath(self::$sampleRepoLocation)
                   ->withSourceBranch(self::BRANCH_LIVE)
                   ->withTargetBranch(self::BRANCH_MASTER);

        $table = $committers->run();

        $this->assertTrue(is_string($table));
    }
}

/** CommittersTest.php ends here */
