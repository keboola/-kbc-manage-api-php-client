<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

class ProjectsMetadataTest extends ClientTestCase
{
    public const TEST_METADATA = [
        [
            'key' => 'test_metadata_key1',
            'value' => 'testval',
        ],
        [
            'key' => 'test.metadata.key1',
            'value' => 'testval',
        ],
    ];

    public const PROVIDER_USER = 'user';

    private $organization;

    public function setUp()
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'spam+spam@keboola.com']);

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
    }

    public function testNormalUserCannotAddMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        try {
            $this->normalUserClient->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testSuperAdminCanAddMetadataInOrgWithAllowAutoJoinFalse()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $metadata = $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);
    }

    public function testSuperAdminCanAddMetadata()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $metadata = $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);
    }

    public function testMaintainerAdminCanAddMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $metadata = $this->normalUserClient->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);
    }

    public function testOrgAdminCanAddMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $metadata = $this->normalUserClient->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);
    }


    public function allowedAddMetadataRoles(): array
    {
        return [
            'admin' => [
                ProjectRole::ADMIN,
            ],
            'share' => [
                ProjectRole::SHARE,
            ],
        ];
    }

    /**
     * @dataProvider allowedAddMetadataRoles
     */
    public function testProjectMemeberCanAddMetadata(string $role)
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        $metadata = $this->normalUserClient->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);
    }

    public function notAllowedAddMetadataRoles(): array
    {
        return [
            'guest' => [
                ProjectRole::GUEST,
            ],
            'read only' => [
                ProjectRole::READ_ONLY,
            ],
        ];
    }

    /**
     * @dataProvider notAllowedAddMetadataRoles
     */
    public function testProjectMemeberCannotAddMetadata(string $role)
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        try {
            $this->normalUserClient->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testSuperAdminWithoutMfaCannotAddMetadata()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        try {
            $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    public function testUpdateProjectMetadata()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $metadata = $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, self::TEST_METADATA);

        $this->assertCount(2, $metadata);

        $md1 = [
            [
                'key' => 'test_metadata_key1',
                'value' => 'updatedtestval',
            ],
        ];

        $md2 = [
            [
                'key' => 'test_metadata_key2',
                'value' => 'testval',
            ],
        ];

        sleep(1);

        // update existing metadata
        $metadataAfterUpdate = $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, $md1);

        $this->assertCount(2, $metadataAfterUpdate);

        $this->assertSame($metadata[1]['id'], $metadataAfterUpdate[1]['id']);
        $this->assertSame('test_metadata_key1', $metadataAfterUpdate[1]['key']);
        $this->assertSame('updatedtestval', $metadataAfterUpdate[1]['value']);
        $this->assertNotSame($metadata[1]['timestamp'], $metadataAfterUpdate[1]['timestamp']);

        // add new metadata
        $newMetadata = $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, $md2);
        $this->assertCount(3, $newMetadata);

        $this->assertNotSame($metadata[0]['id'], $newMetadata[2]['id']);
        $this->assertNotSame($metadata[1]['id'], $newMetadata[2]['id']);
        $this->assertSame('test_metadata_key2', $newMetadata[2]['key']);
        $this->assertSame('testval', $newMetadata[2]['value']);
    }
}
