<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuditTableTestTables extends AbstractMigration
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
        $this->table("audit_source")
            ->addColumn('id',  'integer', ['null' => false, 'identity' => true])
            ->addColumn('int_value',  'integer', ['null' => true])
            ->addColumn('string_value',  'string', ['null' => true, 'limit' => 255])
            ->create();
    }
}
