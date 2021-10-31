<?php

namespace Lexide\Syringe\Command;

use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Schema\SchemaLinter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class SchemaLintCommand extends Command
{
    /**
     * @var SchemaLinter
     */
    protected $linter;

    /**
     * @var string
     */
    protected $schemaFile;

    /**
     * @param string $schemaFile
     * @param SchemaLinter $linter
     */
    public function __construct(string $schemaFile, SchemaLinter $linter)
    {
        parent::__construct();
        $this->schemaFile = $schemaFile;
        $this->linter = $linter;
    }

    public function configure(): void
    {
        $this->setName("lexide:syringe:schema-lint");
    }

    /**
     * {@inheritDoc}
     * @throws ConfigException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!file_exists($this->schemaFile)) {
            throw new ConfigException("The schemaFile '{$this->schemaFile}' does not exist");
        }
        $schema = Yaml::parse(file_get_contents($this->schemaFile));

        $errors = $this->linter->lint($schema);

        $output->writeln("<comment>Schema Lint Results</comment>");
        $output->writeln("");

        foreach ($errors as $error) {
            $replacements = array_map(
                function ($replacement) {
                    return "<info>$replacement</info>";
                },
                $error->getReplacements()
            );

            $output->writeln(sprintf($error->getMessage(), ...$replacements));
        }

        $errorCount = count($errors);

        if ($errorCount > 0) {
            $output->writeln("");
        }

        $output->writeln("Total errors found: " . count($errors));

        return $errorCount === 0? 0: 1;
    }

}