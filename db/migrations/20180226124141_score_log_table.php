<?php


use Phinx\Migration\AbstractMigration;

class ScoreLogTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("
        CREATE TABLE `score_log` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `thing_id` int(11) unsigned NOT NULL,
            `score` decimal(6,4) NOT NULL DEFAULT '0.0000',
            `created_at` datetime NOT NULL,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `thing_id` (`thing_id`),
            CONSTRAINT `fk_thing_id_1` FOREIGN KEY (`thing_id`) REFERENCES `thing` (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    public function down() {
        $this->execute("DROP TABLE `score_log`;");
    } // end method
}
