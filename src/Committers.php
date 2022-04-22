<?php

/**
 * Committers.php
 *
 * Copyright (c) 2022 Christopher M. Fuhrman
 * All rights reserved
 *
 * This program is free software; you can redistribute it and/or
 * modify it under terms of the Simplified BSD License (also
 * known as the "2-Clause License" or "FreeBSD License".)
 *
 * Created on Fri Apr  1 09:56:20 2022 PDT
 */

namespace Git;

require_once __DIR__ . '/../vendor/autoload.php';

use Exception;
use Laminas\Text\Table\Table as Table;
use Laminas\Text\Table\Column as Column;
use Laminas\Text\Table\Exception\InvalidArgumentException;
use Laminas\Text\Table\Exception\OverflowException;
use Laminas\Text\Table\Row as Row;

class Committers
{

    const GIT_CMD_LOG                  = 'log';
    const GIT_CMD_LOG_FORMAT           = "%H::%an::%aE::%at";
    const GIT_CMD_LOG_OUTPUT_SEPARATOR = "::";
    const GIT_CMD_STRING               = "%s " . self::GIT_CMD_LOG . " --format=%s  %s..%s";

    const TABLE_COLUMN_WIDTHS          = [40, 30];

    const GIT_CMD_SEARCH_PATHS         = [
        '/usr/bin/git',
        '/usr/local/bin/git',
        '/usr/pkg/bin/git',
    ];

    /**
     * @var array $branchCommitters
     */
    protected $branchCommitters;

    /**
     * @var string $gitCommand
     */
    protected $gitCommand;

    /**
     * @var string $repositoryPath
     */
    protected $repositoryPath;

    /**
     * @var string $sourceBranch
     */
    protected $sourceBranch;

    /**
     * @var string $targetBranch
     */
    protected $targetBranch;

    /**
     * @var Table $outputTable
     */
    protected $outputTable;

    /**
     * Constructor for this class
     *
     * @param string $gitRepositoryPath
     *
     * @return void
     */
    public function __construct()
    {
        $this->repositoryPath = getcwd();

        // Figure out git command path
        foreach (self::GIT_CMD_SEARCH_PATHS as $path) {
            if (is_file($path) || is_link($path)) {
                $this->gitCommand = $path;
                break;
            }
        }

        // If all else fails, try which(1)
        if (empty($this->gitCommand)) {
            $output     = [];
            $resultCode = 0;

            exec("which git", $output, $resultCode);

            if (isset($output[0]) && is_executable($output[0])) {
                $this->gitCommand = $output[0];
            }
        }
    }

    /**
     * Getter for git command
     *
     * @return string
     */
    public function getGitCommand()
    {
        return $this->gitCommand;
    }

    /**
     * Getter for git repository path
     *
     * @return string
     */
    public function getRepositoryPath()
    {
        return $this->repositoryPath;
    }

    /**
     * Getter for source branch
     *
     * @return string
     */
    public function getSourceBranch()
    {
        return $this->sourceBranch;
    }

    /**
     * Getter for target branch
     *
     * @return string
     */
    public function getTargetBranch()
    {
        return $this->targetBranch;
    }

    /**
     * Setter for git command
     *
     * Example commands:
     *  - /usr/bin/git
     *  - /usr/local/bin/git
     *  - /usr/pkg/bin/git
     *
     * @param string $command
     *
     * @return Committers
     */
    public function withGitCommand(string $command): Committers
    {
        if (! file_exists($command)) {
            throw new \Exception("Command '$command' not found");
        }

        $this->gitCommand = $command;

        return $this;
    }

    /**
     * Setter for path to git repository
     *
     * @param string $path
     *
     * @return Committers
     *
     * @throws \Exception
     */
    public function withGitRepositoryPath(string $path)
    {
        if (! is_dir($path)) {
            throw new \Exception("Repository Path $path does not exist");
        }

        if ((! is_dir("$path/.git")) &&
            (! is_dir("$path/objects"))
        ) {
            exec("ls -al $path/");
            throw new \Exception("Path $path is neither a bare repository nor a git checkout");
        }

        $this->repositoryPath = $path;

        return $this;
    }

    /**
     * Setter for source branch
     *
     * @param string $sourceBranch
     *
     * @return Committers
     */
    public function withSourceBranch(string $sourceBranch): Committers
    {
        $this->sourceBranch = $sourceBranch;

        return $this;
    }

    /**
     * Setter for target branch
     *
     * @param string $targetBranch
     *
     * @return Committers
     */
    public function withTargetBranch(string $targetBranch): Committers
    {
        $this->targetBranch = $targetBranch;

        return $this;
    }

    /**
     * Main script entry point
     *
     * @return string
     */
    public function run()
    {
        if (empty($this->outputTable)) {
            $this->assembleTable();
        }

        return $this->outputTable->__toString();
    }

    /**
     * Magic method for displaying this object
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * @throws \OverflowException
     */
    public function __toString()
    {
        if (empty($this->outputTable)) {
            $this->assembleTable();
        }

        return $this->outputTable->__toString();
    }

    /**
     * Assembles committers
     *
     * @return void
     */
    protected function assembleCommitters()
    {
        $committers = [];
        $output     = [];
        $resultCode = 0;
        $logCommand = sprintf(
            self::GIT_CMD_STRING,
            $this->getGitCommand(),
            self::GIT_CMD_LOG_FORMAT,
            $this->getTargetBranch(),
            $this->getSourceBranch()
        );

        $cwd        = getcwd();

        chdir($this->getRepositoryPath());
        exec($logCommand, $output, $resultCode);
        chdir($cwd);

        if (!empty($resultCode)) {
            throw new \Exception("Command '$logCommand' returned code $resultCode");
        }

        foreach ($output as $committer) {
            $logElements                   = explode(self::GIT_CMD_LOG_OUTPUT_SEPARATOR, $committer);
            $committers[$logElements[2]][] = [
                'hashish'     => $logElements[0],
                'author_name' => $logElements[1],
                'commit_date' => $logElements[3],
            ];
        }

        $this->branchCommitters = $committers;
    }

    /**
     * Assembles Laminas/Text/Table object for later display
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * @throws \OverflowException
     */
    protected function assembleTable()
    {
        if (empty($this->branchCommitters)) {
            $this->assembleCommitters();
        }

        $table = new Table([
            'columnWidths' => self::TABLE_COLUMN_WIDTHS
        ]);

        // Create header
        $table->appendRow(['Committer', 'No. of commits']);

        foreach ($this->branchCommitters as $committer => $commitInfo) {
            $author          = "{$commitInfo[0]['author_name']} <$committer>";
            $committerColumn = new Column("$author");
            $commitCount     = count($commitInfo);
            $countColumn     = new Column("$commitCount");

            $row             = new Row();

            $row->appendColumn($committerColumn);
            $row->appendColumn($countColumn);

            $table->appendRow($row);
        }

        $this->outputTable = $table;
    }
}

/** Committers.php ends here */
