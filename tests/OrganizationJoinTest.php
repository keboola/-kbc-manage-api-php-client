<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class OrganizationJoinTest extends ClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer
     */
    public function setUp()
    {
        parent::setUp();

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }
    }

    public function testSuperAdminJoinOrganization(): void
    {
        $organizationId = $this->organization['id'];

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNull($member);

        $this->client->joinOrganization($organizationId);

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNotNull($member);

        $this->assertArrayHasKey('invitor', $member);
        $this->assertEmpty($member['invitor']);
    }

    public function testMaintainerAdminJoinOrganization(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        $this->normalUserClient->joinOrganization($organizationId);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNotNull($member);

        $this->assertArrayHasKey('invitor', $member);
        $this->assertEmpty($member['invitor']);
    }

    public function testSuperAdminJoinOrganizationError(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($organizationId, [
            "allowAutoJoin" => 0
        ]);

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNull($member);

        try {
            $this->client->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNull($member);
    }

    public function testMaintainerAdminJoinOrganizationError(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($organizationId, [
            "allowAutoJoin" => 0
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testOrganizationAdminJoinOrganizationInvitationDelete()
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        $this->normalUserClient->joinOrganization($organizationId);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNotNull($member);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);
    }

    public function testAdminJoinOrganizationError(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        // project without auto-join
        $this->client->updateOrganization($organizationId, [
            "allowAutoJoin" => 0
        ]);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testOrganizationMemberJoinOrganizationError(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNotNull($member);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        // project without auto-join
        $this->normalUserClient->updateOrganization($organizationId, [
            "allowAutoJoin" => 0
        ]);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    private function findOrganizationMember(int $organizationId, string $userEmail): ?array
    {
        $members = $this->client->listOrganizationUsers($organizationId);

        foreach ($members as $member) {
            if ($member['email'] === $userEmail) {
                return $member;
            }
        }

        return null;
    }
}