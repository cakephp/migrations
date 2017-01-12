<?php
namespace Migrations;

use Cake\Core\Plugin;
use Phinx\Migration\AbstractTemplateCreation;

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
     * Once the migration file has been created, this method will be called, allowing any additional
     * processing, specific to the template to be performed.
     *
     * @param string $migrationFilename The name of the newly created migration.
     * @param string $className The class name.
     * @param string $baseClassName The name of the base class.
     * @return void
     */
    public function postMigrationCreation($migrationFilename, $className, $baseClassName)
    {
        // TODO: Implement postMigrationCreation() method.
    }
}