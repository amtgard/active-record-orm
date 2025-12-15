<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitIntegDb extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        return;
        $this->table('integ')
            ->addColumn('int_value',  'integer', ['null' => true])
            ->addColumn('string_value', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('datetime_value', 'datetime', ['null' => true])
            ->addColumn('blob_value', 'blob', ['null' => true])
            ->addColumn('text_value', 'text', ['null' => true])
            ->create();
    }
}
