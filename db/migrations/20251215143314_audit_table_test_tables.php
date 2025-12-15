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
            ->addColumn('int_value',  'integer', ['null' => true])
            ->addColumn('string_value', 'string', ['limit' => 255, 'null' => true])
            ->create();

        $this->table("audit_source_audit_log")
            ->addColumn('record_id',  'integer', ['null' => false])
            ->addColumn('log_datetime',  'datetime', ['null' => false])
            ->addColumn('fields',  'text', ['limit' => 16535, 'null' => true])
            ->addColumn('action',  'enum', ['values' => ["insert", "update", "delete"]])
            ->addColumn('by_whom_id',  'integer', ['null' => true])

            ->addColumn('int_value',  'integer', ['null' => true])
            ->addColumn('string_value', 'string', ['limit' => 255, 'null' => true])
            ->create();
    }
}
