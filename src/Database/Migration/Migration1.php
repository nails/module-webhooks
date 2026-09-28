<?php

namespace Nails\Webhooks\Database\Migration;

use Nails\Common\Interfaces\Database\Migration;
use Nails\Common\Traits\Database\Migration as MigrationTrait;

class Migration1 implements Migration
{
    use MigrationTrait;

    public function execute()
    {
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}webhook_instance` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `definition_class` varchar(255) NOT NULL,
                `definition_slug` varchar(255) NOT NULL,
                `token` char(64) NOT NULL,
                `label` varchar(255) NOT NULL,
                `is_enabled` tinyint(1) unsigned NOT NULL DEFAULT 1,
                `secret` varchar(255) DEFAULT NULL,
                `config` text,
                `subscription_status` varchar(255) DEFAULT NULL,
                `is_deleted` tinyint(1) unsigned NOT NULL DEFAULT 0,
                `created` datetime NOT NULL,
                `created_by` int(11) unsigned DEFAULT NULL,
                `modified` datetime NOT NULL,
                `modified_by` int(11) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`),
                KEY `definition_slug` (`definition_slug`),
                KEY `created_by` (`created_by`),
                KEY `modified_by` (`modified_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}webhook_delivery` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `uuid` char(36) NOT NULL,
                `definition_class` varchar(255) NOT NULL,
                `definition_slug` varchar(255) NOT NULL,
                `instance_id` int(11) unsigned DEFAULT NULL,
                `scope` varchar(191) NOT NULL,
                `status` varchar(20) NOT NULL,
                `http_status` smallint(5) unsigned NOT NULL,
                `idempotency_key` varchar(191) DEFAULT NULL,
                `success_key` varchar(191) GENERATED ALWAYS AS (
                    CASE
                        WHEN `status` IN ('accepted', 'ignored') THEN `idempotency_key`
                        ELSE NULL
                    END
                ) STORED,
                `summary` varchar(500) DEFAULT NULL,
                `duration_ms` int(11) unsigned DEFAULT NULL,
                `log_file` varchar(255) DEFAULT NULL,
                `created` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uuid` (`uuid`),
                UNIQUE KEY `scope_success_key` (`scope`, `success_key`),
                KEY `definition_created` (`definition_slug`, `created`),
                KEY `instance_created` (`instance_id`, `created`),
                KEY `scope_key` (`scope`, `idempotency_key`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}webhook_delivery_instance`
                    FOREIGN KEY (`instance_id`)
                    REFERENCES `{{NAILS_DB_PREFIX}}webhook_instance` (`id`)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}
