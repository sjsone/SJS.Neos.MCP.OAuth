<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603140000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sjs_neos_mcp_oauth_domain_model_client (
            persistence_object_identifier VARCHAR(40) NOT NULL,
            party VARCHAR(40) NOT NULL,
            name VARCHAR(255) NOT NULL,
            clientid VARCHAR(255) NOT NULL,
            clientsecrethash VARCHAR(255) NOT NULL,
            redirecturis LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\',
            grants LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\',
            scopes LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\',
            createdat DATETIME NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            INDEX IDX_SJS_NEOS_MCP_OAUTH_CLIENT_PARTY (party),
            UNIQUE INDEX UNQ_SJS_NEOS_MCP_OAUTH_CLIENT_CLIENTID (clientid),
            PRIMARY KEY(persistence_object_identifier)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE sjs_neos_mcp_oauth_domain_model_accesstoken (
            persistence_object_identifier VARCHAR(40) NOT NULL,
            client VARCHAR(40) NOT NULL,
            identifier VARCHAR(255) NOT NULL,
            expirydatetime DATETIME NOT NULL,
            useridentifier VARCHAR(255) DEFAULT NULL,
            scopes LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\',
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            createdat DATETIME DEFAULT NULL,
            INDEX IDX_SJS_NEOS_MCP_OAUTH_ACCESSTOKEN_CLIENT (client),
            INDEX IDX_SJS_NEOS_MCP_OAUTH_ACCESSTOKEN_IDENTIFIER (identifier),
            PRIMARY KEY(persistence_object_identifier)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken (
            persistence_object_identifier VARCHAR(40) NOT NULL,
            accesstoken VARCHAR(40) NOT NULL,
            identifier VARCHAR(255) NOT NULL,
            expirydatetime DATETIME NOT NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            INDEX IDX_SJS_NEOS_MCP_OAUTH_REFRESHTOKEN_ACCESSTOKEN (accesstoken),
            INDEX IDX_SJS_NEOS_MCP_OAUTH_REFRESHTOKEN_IDENTIFIER (identifier),
            PRIMARY KEY(persistence_object_identifier)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE sjs_neos_mcp_oauth_domain_model_authcode (
            persistence_object_identifier VARCHAR(40) NOT NULL,
            client VARCHAR(40) NOT NULL,
            identifier VARCHAR(255) NOT NULL,
            expirydatetime DATETIME NOT NULL,
            useridentifier VARCHAR(255) DEFAULT NULL,
            scopes LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\',
            redirecturi VARCHAR(255) NOT NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            INDEX IDX_SJS_NEOS_MCP_OAUTH_AUTHCODE_CLIENT (client),
            INDEX IDX_SJS_NEOS_MCP_OAUTH_AUTHCODE_IDENTIFIER (identifier),
            PRIMARY KEY(persistence_object_identifier)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sjs_neos_mcp_oauth_domain_model_authcode');
        $this->addSql('DROP TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken');
        $this->addSql('DROP TABLE sjs_neos_mcp_oauth_domain_model_accesstoken');
        $this->addSql('DROP TABLE sjs_neos_mcp_oauth_domain_model_client');
    }
}
