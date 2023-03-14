<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class AssignAdminFeatureTest extends ClientTestCase
{
    private array $organization;

    public function setUp(): void
    {
        parent::setUp();
        $this->cleanupFeatures($this->testFeatureName(), 'admin');

        // 1. add a user as placeholder for the maintainer, later I will need to remove a $this->client and it wouldn't work if there was only one
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'devel-tests+spam@keboola.com']);

        // 2. Resetting the settings maintainer admins created in tests, because in some tests we add a user to the maintainer to make it an maintainer admin.
        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        // 3. create new org for tests, because in some tests we add a user to the org to make it an org admin.
        // This way we ensure that every time the test is run, everything is reset
        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        // 4. add a user as placeholder for the organization, later I will need to remove a $this->client and it wouldn't work if there was only one
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        // 5. remove superAdmin from org, we want to have super admin without maintainer and org. admin
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    /**
     * @dataProvider canBeManageByAdminProvider
     */
    public function testSuperAdminCanManageAdminFeatureForAnybody(bool $canBeManageByAdmin)
    {
        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            $canBeManageByAdmin,
            true
        );

        $this->client->addUserFeature($this->normalUser['email'], $featureName);

        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $this->client->removeUserFeature($this->normalUser['email'], $featureName);
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertNotContains($featureName, $user['features']);
    }

    public function testSuperAdminCannotManageFeatureCannotBeManagedViaAPI()
    {
        $featureName = $this->testFeatureName();
        $feature = $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            false,
            false
        );

        try {
            $this->client->addUserFeature($this->normalUser['email'], $featureName);
            $this->fail('The feature "%s" can\'t be added via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => false,
            'canBeManagedViaAPI' => true,
        ]);

        $this->client->addUserFeature($this->normalUser['email'], $featureName);

        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => false,
            'canBeManagedViaAPI' => false,
        ]);

        try {
            $this->client->removeUserFeature($this->normalUser['email'], $featureName);
            $this->fail('The feature "%s" can\'t be removed via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function canBeManageByAdminProvider(): array
    {
        return [
            'admin can manage' => [true],
            'admin cannot manage' => [false],
        ];
    }

    public function testUserCanManageOwnFeatures()
    {
        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            true,
            true
        );

        $this->normalUserClient->addUserFeature($this->normalUser['email'], $featureName);

        // assert user has newly created feature
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $this->normalUserClient->removeUserFeature($this->normalUser['email'], $featureName);
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertNotContains($featureName, $user['features']);
    }

    public function testUserCanNotManageOtherUserFeatures()
    {
        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            true,
            true
        );

        try {
            $this->normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertEquals('You can\'t access other users', $e->getMessage());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $this->normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to remove feature from other user');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertEquals('You can\'t access other users', $e->getMessage());
        }
    }

    public function testMaintainerAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            true,
            true
        );

        try {
            $this->normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $this->normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);
    }

    public function testOrgAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            true,
            true
        );

        try {
            $this->normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $this->normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);
    }
}
