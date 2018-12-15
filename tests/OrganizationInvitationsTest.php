<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class OrganizationInvitationsTest extends ClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
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

        foreach ($this->normalUserClient->listMyOrganizationInvitations() as $invitation) {
            $this->normalUserClient->declineMyOrganizationInvitation($invitation['id']);
        }

        foreach ($this->client->listMyOrganizationInvitations() as $invitation) {
            $this->client->declineMyOrganizationInvitation($invitation['id']);
        }
    }

    public function autoJoinProvider(): array
    {
        return [
            [
                true,
            ],
            [
                false,
            ],
        ];
    }

    public function testSuperAdminCanInvite(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        $invitation = $this->client->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        $invitee = $this->client->getUser($inviteeEmail);

        $this->assertEquals($invitee['id'], $invitation['user']['id']);
        $this->assertEquals($invitee['email'], $invitation['user']['email']);
        $this->assertEquals($invitee['name'], $invitation['user']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->client->getOrganizationInvitation($organizationId, $invitation['id']));

        $this->client->cancelOrganizationInvitation($organizationId, $invitation['id']);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    public function testSuperAdminCannotInviteIfAllowAutoJoinIsDisabled(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($organizationId, [
            'allowAutoJoin' => false
        ]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        try {
            $this->client->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Inviting someone to organization without allowAutoJoin should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    public function testMaintainerAdminCanInvite(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        $invitation = $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        $invitee = $this->client->getUser($inviteeEmail);

        $this->assertEquals($invitee['id'], $invitation['user']['id']);
        $this->assertEquals($invitee['email'], $invitation['user']['email']);
        $this->assertEquals($invitee['name'], $invitation['user']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->normalUserClient->getOrganizationInvitation($organizationId, $invitation['id']));

        $this->normalUserClient->cancelOrganizationInvitation($organizationId, $invitation['id']);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    public function testMaintainerAdminCannotInviteIfAllowAutoJoinIsDisabled(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($organizationId, [
            'allowAutoJoin' => false
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Inviting someone to organization without allowAutoJoin should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testRandomAdminCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($organizationId, [
            'allowAutoJoin' => $allowAutoJoin
        ]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        try {
            $this->normalUserClient->listOrganizationInvitations($organizationId);
            $this->fail('Admin from ouside the  organisation should not be able to list invitations');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Admin from ouside the organisation should not be able to invite someone');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminCanInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($organizationId, [
            'allowAutoJoin' => $allowAutoJoin
        ]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        $invitation = $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        $invitee = $this->client->getUser($inviteeEmail);

        $this->assertEquals($invitee['id'], $invitation['user']['id']);
        $this->assertEquals($invitee['email'], $invitation['user']['email']);
        $this->assertEquals($invitee['name'], $invitation['user']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->normalUserClient->getOrganizationInvitation($organizationId, $invitation['id']));

        $this->normalUserClient->cancelOrganizationInvitation($organizationId, $invitation['id']);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    public function testAdminAcceptsInvitation()
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($organizationId, $invitation['organization']['id']);
        $this->assertEquals($this->organization['name'], $invitation['organization']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->assertEquals($invitation, $this->normalUserClient->getMyOrganizationInvitation($invitation['id']));

        $this->normalUserClient->acceptMyOrganizationInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNotNull($member);

        $this->assertArrayHasKey('invitor', $member);

        $this->assertNotEmpty($member['invitor']);
        $this->assertNotEmpty($member['created']);
        $this->assertEquals($this->superAdmin['id'], $member['invitor']['id']);
        $this->assertEquals($this->superAdmin['email'], $member['invitor']['email']);
        $this->assertEquals($this->superAdmin['name'], $member['invitor']['name']);
    }

    public function testAdminDeclinesInvitation()
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($organizationId, $invitation['organization']['id']);
        $this->assertEquals($this->organization['name'], $invitation['organization']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->normalUserClient->declineMyOrganizationInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testCannotInviteAlreadyInvitedUser()
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        // send invitation twice
        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);
            $this->fail('Inviting user to organization twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('invited', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);
    }

    public function testCannotInviteExistingMember()
    {
        $inviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->addUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Inviting existing member to organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('member', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);
    }

    public function testCannotInviteYourself()
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);
            $this->fail('Inviting yourself to organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('You cannot invite yourself', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);
    }

    public function testRemovedMemberOfOrgCannotCancelTheirInvitations()
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->removeUserFromOrganization($organizationId, $this->normalUser['id']);

        // normal admin
        try {
            $this->normalUserClient->cancelOrganizationInvitation($organizationId, $invitation['id']);
            $this->fail('Canceling invitations by removed user should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);
    }

    public function testDeletingOrganizationRemovesInvitations()
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $this->client->deleteOrganization($organizationId);

        $invitations = $this->client->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);
    }

    public function testAddingAdminToOrganizationDeletesCorrespondingInvitation(): void
    {
        $inviteeEmail = $this->normalUser['email'];
        $secondInviteeEmail = 'spam@keboola.com';
        $organizationId = $this->organization['id'];

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
        $this->client->inviteUserToOrganization($organizationId, ['email' => $secondInviteeEmail]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(2, $invitations);

        $this->client->addUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNotNull($member);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($secondInviteeEmail, $invitation['user']['email']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);
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
