<?php
namespace Migrations;

use Cake\Core\Plugin;
use Cake\Utility\Inflector;
use Phinx\Migration\AbstractTemplateCreation;
use Phinx\Util\Util;

/**
 * Class TemplateCreation
 *
 * Custom migration template class
 */
class TemplateCreation extends AbstractTemplateCreation
{

    /**
     * Get the migration template.
     *
     * This will be the content that Phinx will amend to generate the migration file.
     *
     * @return string The content of the template for Phinx to amend.
     */
    public function getMigrationTemplate()
    {
        $path = Plugin::path('Migrations') . 'src' . DS . 'Template' . DS . 'Phinx' . DS . 'create.php.template';
        return file_get_contents($path);
    }

    /**
     * Post Migration Creation.
     *
     * Will rename the file to follow the CakePHP conventions around migration filename (in CamelCase).
     *
     * @param string $migrationFilename The name of the newly created migration.
     * @param string $className The class name.
     * @param string $baseClassName The name of the base class.
     * @return void
     */
    public function postMigrationCreation($migrationFilename, $className, $baseClassName)
    {
        $path = dirname($migrationFilename) . DS;
        $name = Inflector::camelize($className);
        $newPath = $path . Util::getCurrentTimestamp() . '_' . $name . '.php';

        $this->output->writeln('<info>renaming file in CamelCase to follow CakePHP convention...</info>');
        if (rename($migrationFilename, $newPath)) {
            $this->output->writeln(sprintf('<info>File successfully renamed to %s</info>', $newPath));
        } else {
            $this->output->writeln(sprintf('<info>An error occurred while renaming file to %s</info>', $newPath));
        }
    }
}
