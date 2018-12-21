<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class UsersTest extends ClientTestCase
{

    public function testGetUser()
    {
        $token = $this->client->verifyToken();
        $userEmail = $token['user']['email'];
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($userId, $user['id']);
        $initialFeaturesCount = count($user['features']);

        $feature = 'manage-feature-test-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($feature, 'admin', $feature);
        $this->client->addUserFeature($userEmail, $feature);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($initialFeaturesCount + 1, count($user['features']));
        $this->assertContains($feature, $user['features']);

        $feature2 = 'manage-feature-test-2-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($feature2, 'admin', $feature2);
        $this->client->addUserFeature($userId, $feature2);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($initialFeaturesCount + 2, count($user['features']));
        $this->assertContains($feature, $user['features']);

        $this->client->removeUserFeature($userId, $feature);
        $this->client->removeUserFeature($userId, $feature2);

        $user = $this->client->getUser($userId);
        $this->assertEquals($initialFeaturesCount, count($user['features']));
    }

    public function testGetNonexistentUser()
    {
        try {
            $this->client->getUser('nonexistent.user@keboola.com');
            $this->fail('nonexistent.user@keboola.com not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testAddNonexistentFeature()
    {
        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $featureName = 'random-feature-' . $this->getRandomFeatureSuffix();

        try {
            $this->client->addUserFeature($token['user']['id'], $featureName);
            $this->fail('Feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testAddUserFeatureTwice()
    {
        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $initialFeaturesCount = count($user['features']);

        $newFeature = 'new-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($newFeature, 'admin', $newFeature);
        $this->client->addUserFeature($userId, $newFeature);

        $user = $this->client->getUser($userId);

        $this->assertSame($initialFeaturesCount + 1, count($user['features']));

        try {
            $this->client->addUserFeature($userId, $newFeature);
            $this->fail('Feature already added');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        $user = $this->client->getUser($userId);

        $this->assertSame($initialFeaturesCount + 1, count($user['features']));
    }

    public function testUpdateUser()
    {
        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $oldUserName = $user['name'];
        $newUserName = 'Rename ' . date('y-m-d H:i:s');

        $updatedUser = $this->client->updateUser($userId, ['name' => $newUserName]);

        $this->assertNotEquals($oldUserName, $updatedUser['name']);
        $this->assertEquals($newUserName, $updatedUser['name']);
    }

    public function testDisableUserMFA()
    {
        $token = $this->normalUserClient->verifyToken();
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $this->assertArrayHasKey('mfaEnabled', $user);
        $this->assertFalse($user['mfaEnabled']);

        try {
            $this->client->disableUserMFA($userId);
            $this->fail("you cannot disable mfa for user having mfa disabled");
        } catch(ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    public function testNormalUserShouldNotBeAbleDisableMFA()
    {
        $token = $this->normalUserClient->verifyToken();
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $this->assertArrayHasKey('mfaEnabled', $user);
        $this->assertFalse($user['mfaEnabled']);

        try {
            $this->normalUserClient->disableUserMFA($userId);
            $this->fail("normal user should not be able to enable mfa via thea api");
        } catch(ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testsRemoveUserFromEverywhere()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, ['name' => 'ToRemoveOrg']);
        $project = $this->client->createProject($organization['id'], ['name' => 'ToRemoveProj']);
        $email = 'remove' . uniqid() . '@keboola.com';
        $this->client->addUserToProject($project['id'], ['email' => $email]);
        $user = $this->client->getUser($email);
        $this->client->addUserToOrganization($organization['id'], ['email' => $user['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $user['email']]);

        $this->client->removeUser($email);

        $usersInProject = $this->client->listProjectUsers($project['id']);
        foreach ($usersInProject as $userInProject) {
            if ($userInProject['id'] === $user['id']) {
                $this->fail('User has not been deleted from project');
            }
        }

        $usersInOrganization = $this->client->listOrganizationUsers($organization['id']);
        foreach ($usersInOrganization as $userInOrganization) {
            if ($userInOrganization['id'] === $user['id']) {
                $this->fail('User has not been deleted from organization');
            }
        }

        $usersInMaintainer = $this->client->listMaintainerMembers($this->testMaintainerId);
        foreach ($usersInMaintainer as $userInMaintainer) {
            if ($userInMaintainer['id'] === $user['id']) {
                $this->fail('User has not been deleted from maintainer');
            }
        }

        $deletedUser = $this->client->getUser($user['id']);

        $this->assertSame('DELETED', $deletedUser['email'], 'User e-mail has not been deleted');
        $this->assertSame(false, $deletedUser['mfaEnabled'], 'User password has not been deleted');
        $this->assertSame('DELETED', $deletedUser['name'], 'User name has not been deleted');
    }
}
