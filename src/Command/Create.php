<?php
/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Command;

use Migrations\ConfigurationTrait;
use Phinx\Console\Command\Create as CreateCommand;
use Phinx\Util\Util;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends CreateCommand
{

    use ConfigurationTrait {
        execute as parentExecute;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('create')
            ->setDescription('Create a new migration')
            ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the migration?')
            ->setHelp(sprintf(
                '%sCreates a new database migration file%s',
                PHP_EOL,
                PHP_EOL
            ))
            ->addOption('plugin', 'p', InputOption::VALUE_REQUIRED, 'The plugin the file should be created for')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'The datasource connection to use')
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'The folder where migrations are in')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use an alternative template')
            ->addOption(
                'class',
                'l',
                InputOption::VALUE_REQUIRED,
                'Use a class implementing "' . parent::CREATION_INTERFACE . '" to generate the template'
            );
    }

    /**
     * Overrides the action execute method in order to vanish the idea of environments
     * from phinx. CakePHP does not believe in the idea of having in-app environments
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input the input object
     * @param \Symfony\Component\Console\Output\OutputInterface $output the output object
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->beforeExecute($input, $output);
        $this->bootstrap($input, $output);

        // get the migration path from the config
        $path = $this->getConfig()->getMigrationPath();

        if (!file_exists($path)) {
            $helper   = $this->getHelper('question');
            $question = $this->getCreateMigrationDirectoryQuestion();

            if ($helper->ask($input, $output, $question)) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifyMigrationDirectory($path);

        $path = realpath($path);
        $className = $input->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" already exists',
                $className
            ));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        // Get the alternative template and static class options from the config, but only allow one of them.
        $defaultAltTemplate = $this->getConfig()->getTemplateFile();
        $defaultCreationClassName = $this->getConfig()->getTemplateClass();
        if ($defaultAltTemplate && $defaultCreationClassName){
            throw new \InvalidArgumentException('Cannot define template:class and template:file at the same time');
        }

        // Get the alternative template and static class options from the command line, but only allow one of them.
        $altTemplate = $input->getOption('template');
        $creationClassName = $input->getOption('class');
        if ($altTemplate && $creationClassName) {
            throw new \InvalidArgumentException('Cannot use --template and --class at the same time');
        }

        // If no commandline options then use the defaults.
        if (!$altTemplate && !$creationClassName){
            $altTemplate = $defaultAltTemplate;
            $creationClassName = $defaultCreationClassName;
        }

        // Verify the alternative template file's existence.
        if ($altTemplate && !is_file($altTemplate)) {
            throw new \InvalidArgumentException(sprintf(
                'The alternative template file "%s" does not exist',
                $altTemplate
            ));
        }

        // Verify that the template creation class (or the aliased class) exists and that it implements the required interface.
        $aliasedClassName  = null;
        if ($creationClassName) {
            // Supplied class does not exist, is it aliased?
            if (!class_exists($creationClassName)) {
                $aliasedClassName = $this->getConfig()->getAlias($creationClassName);
                if ($aliasedClassName && !class_exists($aliasedClassName)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The class "%s" via the alias "%s" does not exist',
                        $aliasedClassName,
                        $creationClassName
                    ));
                } elseif (!$aliasedClassName) {
                    throw new \InvalidArgumentException(sprintf(
                        'The class "%s" does not exist',
                        $creationClassName
                    ));
                }
            }

            // Does the class implement the required interface?
            if (!$aliasedClassName && !is_subclass_of($creationClassName, self::CREATION_INTERFACE)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" does not implement the required interface "%s"',
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            } elseif ($aliasedClassName && !is_subclass_of($aliasedClassName, self::CREATION_INTERFACE)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" via the alias "%s" does not implement the required interface "%s"',
                    $aliasedClassName,
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            }
        }

        // Use the aliased class.
        $creationClassName = $aliasedClassName ?: $creationClassName;

        // Determine the appropriate mechanism to get the template
        if ($creationClassName) {
            // Get the template from the creation class
            $creationClass = new $creationClassName($input, $output);
            $contents = $creationClass->getMigrationTemplate();
        } else {
            // Load the alternative template if it is defined.
            $contents = file_get_contents($altTemplate ?: $this->getMigrationTemplateFilename());
        }

        // inject the class names appropriate to this migration
        $classes = [
            '$useClassName'  => $this->getConfig()->getMigrationBaseClassName(false),
            '$className'     => $className,
            '$version'       => Util::getVersionFromFileName($fileName),
            '$baseClassName' => $this->getConfig()->getMigrationBaseClassName(true),
        ];
        $contents = strtr($contents, $classes);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output->writeln('<info>using migration base class</info> ' . $classes['$useClassName']);

        if (!empty($altTemplate)) {
            $output->writeln('<info>using alternative template</info> ' . $altTemplate);
        } elseif (!empty($creationClassName)) {
            $output->writeln('<info>using template creation class</info> ' . $creationClassName);
        } else {
            $output->writeln('<info>using default template</info>');
        }

        $output->writeln('<info>created</info> ' . str_replace(getcwd(), '', $filePath));

        // Do we need to do the post creation call to the creation class?
        if ($creationClassName) {
            $creationClass->postMigrationCreation($filePath, $className, $this->getConfig()->getMigrationBaseClassName());
        }
    }
}
