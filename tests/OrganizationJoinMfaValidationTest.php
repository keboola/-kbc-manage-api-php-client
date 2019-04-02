<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class OrganizationJoinMfaValidationTest extends ClientTestCase
{
    private const DUMMY_USER_EMAIL = 'spam+spam@keboola.com';

    /** @var Client */
    private $normalUserWithMfaClient;

    private $normalUserWithMfa;

    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
     */
    public function setUp()
    {
        parent::setUp();

        $this->normalUserWithMfaClient = new Client([
            'token' => getenv('KBC_TEST_ADMIN_WITH_MFA_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);

        $this->normalUserWithMfa = $this->normalUserWithMfaClient->verifyToken()['user'];

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => self::DUMMY_USER_EMAIL]);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => self::DUMMY_USER_EMAIL]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testSuperAdminCannotJoinOrganization()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $member = $this->findOrganizationMember($this->organization['id'], self::DUMMY_USER_EMAIL);
        $this->client->removeUserFromOrganization($this->organization['id'], $member['id']);

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);

        try {
            $this->client->joinOrganization($this->organization['id']);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $member = $this->findOrganizationMember($this->organization['id'], $this->superAdmin['email']);
        $this->assertNull($member);
    }

    public function testMaintainerAdminWithoutMfaCannotJoinOrganization()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $member = $this->findOrganizationMember($this->organization['id'], self::DUMMY_USER_EMAIL);
        $this->client->removeUserFromOrganization($this->organization['id'], $member['id']);

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);

        try {
            $this->normalUserClient->joinOrganization($this->organization['id']);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $member = $this->findOrganizationMember($this->organization['id'], $this->normalUser['email']);
        $this->assertNull($member);
    }
}
