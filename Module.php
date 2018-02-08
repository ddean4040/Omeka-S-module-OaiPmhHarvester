<?php
namespace OaiPmhHarvester;

use Omeka\Module\AbstractModule;
use Omeka\Entity\Job;
use Zend\ModuleManager\ModuleManager;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function init(ModuleManager $moduleManager)
    {
        ini_set( 'display_errors', 'On' );
    	#require_once __DIR__ . '/vendor/autoload.php';
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = "
            CREATE TABLE oai_pmh_harvester_import (
                id INT AUTO_INCREMENT NOT NULL, 
                job_id INT NOT NULL, 
                undo_job_id INT DEFAULT NULL, 
                stats INT NOT NULL, 
                comment VARCHAR(255) DEFAULT NULL, 
                resource_type VARCHAR(255) NOT NULL,
                has_err TINYINT(1) NOT NULL,
                UNIQUE INDEX UNIQ_27B50881BE04EA9 (job_id), 
                UNIQUE INDEX UNIQ_27B508814C276F75 (undo_job_id), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
            CREATE TABLE oai_pmh_harvester_entity (
                id INT AUTO_INCREMENT NOT NULL, 
                job_id INT NOT NULL, 
                entity_id INT NOT NULL, 
                resource_type VARCHAR(255) NOT NULL, 
                INDEX IDX_74D382F4BE04EA9 (job_id), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
            ALTER TABLE oai_pmh_harvester_import ADD CONSTRAINT FK_27B50881BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);
            ALTER TABLE oai_pmh_harvester_import ADD CONSTRAINT FK_27B508814C276F75 FOREIGN KEY (undo_job_id) REFERENCES job (id);
            ALTER TABLE oai_pmh_harvester_entity ADD CONSTRAINT FK_74D382F4BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);
        ";
        $connection->exec($sql);
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec("ALTER TABLE oai_pmh_harvester_entity DROP FOREIGN KEY FK_74D382F4BE04EA9;");
        $connection->exec("ALTER TABLE oai_pmh_harvester_import DROP FOREIGN KEY FK_27B50881BE04EA9;");
        $connection->exec("ALTER TABLE oai_pmh_harvester_import DROP FOREIGN KEY FK_27B508814C276F75;");
        $connection->exec("DROP TABLE oai_pmh_harvester_entity;");
        $connection->exec("DROP TABLE oai_pmh_harvester_import;");
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
    }
}
