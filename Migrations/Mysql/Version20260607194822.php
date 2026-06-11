<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607194822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDb1010Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDb1010Platform'."
        );

        $this->addSql('DROP INDEX IDX_SJS_NEOS_MCP_OAUTH_ACCESSTOKEN_IDENTIFIER ON sjs_neos_mcp_oauth_domain_model_accesstoken');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_accesstoken CHANGE client client VARCHAR(40) DEFAULT NULL, CHANGE revoked revoked TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_accesstoken ADD CONSTRAINT FK_E4C4D781C7440455 FOREIGN KEY (client) REFERENCES sjs_neos_mcp_oauth_domain_model_client (persistence_object_identifier)');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_accesstoken RENAME INDEX idx_sjs_neos_mcp_oauth_accesstoken_client TO IDX_E4C4D781C7440455');
        $this->addSql('DROP INDEX IDX_SJS_NEOS_MCP_OAUTH_AUTHCODE_IDENTIFIER ON sjs_neos_mcp_oauth_domain_model_authcode');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_authcode CHANGE client client VARCHAR(40) DEFAULT NULL, CHANGE revoked revoked TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_authcode ADD CONSTRAINT FK_7EB79694C7440455 FOREIGN KEY (client) REFERENCES sjs_neos_mcp_oauth_domain_model_client (persistence_object_identifier)');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_authcode RENAME INDEX idx_sjs_neos_mcp_oauth_authcode_client TO IDX_7EB79694C7440455');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client CHANGE party party VARCHAR(40) DEFAULT NULL, CHANGE enabled enabled TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client ADD CONSTRAINT FK_8C27EA0689954EE0 FOREIGN KEY (party) REFERENCES neos_party_domain_model_abstractparty (persistence_object_identifier)');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client RENAME INDEX unq_sjs_neos_mcp_oauth_client_clientid TO UNIQ_8C27EA067F98CD1C');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client RENAME INDEX idx_sjs_neos_mcp_oauth_client_party TO IDX_8C27EA0689954EE0');
        $this->addSql('DROP INDEX IDX_SJS_NEOS_MCP_OAUTH_REFRESHTOKEN_IDENTIFIER ON sjs_neos_mcp_oauth_domain_model_refreshtoken');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken CHANGE accesstoken accesstoken VARCHAR(40) DEFAULT NULL, CHANGE revoked revoked TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken ADD CONSTRAINT FK_E010FA49F4CBB726 FOREIGN KEY (accesstoken) REFERENCES sjs_neos_mcp_oauth_domain_model_accesstoken (persistence_object_identifier)');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken RENAME INDEX idx_sjs_neos_mcp_oauth_refreshtoken_accesstoken TO IDX_E010FA49F4CBB726');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDb1010Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDb1010Platform'."
        );

        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken DROP FOREIGN KEY FK_E010FA49F4CBB726');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken CHANGE accesstoken accesstoken VARCHAR(40) NOT NULL, CHANGE revoked revoked TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX IDX_SJS_NEOS_MCP_OAUTH_REFRESHTOKEN_IDENTIFIER ON sjs_neos_mcp_oauth_domain_model_refreshtoken (identifier)');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_refreshtoken RENAME INDEX idx_e010fa49f4cbb726 TO IDX_SJS_NEOS_MCP_OAUTH_REFRESHTOKEN_ACCESSTOKEN');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client DROP FOREIGN KEY FK_8C27EA0689954EE0');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client CHANGE party party VARCHAR(40) NOT NULL, CHANGE enabled enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client RENAME INDEX uniq_8c27ea067f98cd1c TO UNQ_SJS_NEOS_MCP_OAUTH_CLIENT_CLIENTID');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_client RENAME INDEX idx_8c27ea0689954ee0 TO IDX_SJS_NEOS_MCP_OAUTH_CLIENT_PARTY');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_authcode DROP FOREIGN KEY FK_7EB79694C7440455');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_authcode CHANGE client client VARCHAR(40) NOT NULL, CHANGE revoked revoked TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX IDX_SJS_NEOS_MCP_OAUTH_AUTHCODE_IDENTIFIER ON sjs_neos_mcp_oauth_domain_model_authcode (identifier)');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_authcode RENAME INDEX idx_7eb79694c7440455 TO IDX_SJS_NEOS_MCP_OAUTH_AUTHCODE_CLIENT');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_accesstoken DROP FOREIGN KEY FK_E4C4D781C7440455');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_accesstoken CHANGE client client VARCHAR(40) NOT NULL, CHANGE revoked revoked TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX IDX_SJS_NEOS_MCP_OAUTH_ACCESSTOKEN_IDENTIFIER ON sjs_neos_mcp_oauth_domain_model_accesstoken (identifier)');
        $this->addSql('ALTER TABLE sjs_neos_mcp_oauth_domain_model_accesstoken RENAME INDEX idx_e4c4d781c7440455 TO IDX_SJS_NEOS_MCP_OAUTH_ACCESSTOKEN_CLIENT');
    }
}
