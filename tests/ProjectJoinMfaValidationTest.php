<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class ProjectJoinMfaValidationTest extends ClientTestCase
{
    private const DUMMY_USER_EMAIL = 'spam+spam@keboola.com';

    /** @var Client */
    private $normalUserWithMfaClient;

    private $normalUserWithMfa;

    private $organization;

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

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);


        foreach ($this->normalUserClient->listMyProjectJoinRequests() as $joinRequest) {
            $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);
        }

        foreach ($this->client->listMyProjectJoinRequests() as $joinRequest) {
            $this->client->deleteMyProjectJoinRequest($joinRequest['id']);
        }
    }

    public function testAdminCannotJoinProject()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled();

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        try {
            $this->client->joinProject($projectId);
            $this->fail('Joining a project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);
    }

    private function createProjectWithAdminHavingMfaEnabled(): int
    {
        $project = $this->normalUserWithMfaClient->createProject($this->organization['id'], [
            'name' => 'My test',
        ]);

        return $project['id'];
    }
}