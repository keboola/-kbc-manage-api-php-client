<?php

namespace Keboola\ManageApiTest;

use Generator;
use Keboola\ManageApi\ClientException;

class FeaturesTest extends ClientTestCase
{
    /**
     * @dataProvider featureProvider
     */
    public function testCreateListAndDeleteFeature(array $createFeature, array $expectedFeature)
    {
        $this->client->createFeature(
            $createFeature['name'],
            $createFeature['type'],
            $createFeature['description'],
            $createFeature['canBeManageByAdmin'],
            $createFeature['canBeManagedViaAPI']
        );

        $features = $this->client->listFeatures();

        $featureFound = null;

        foreach ($features as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }

        $this->assertTrue($featureFound !== null);
        $this->assertSame($expectedFeature['name'], $featureFound['name']);
        $this->assertSame($expectedFeature['type'], $featureFound['type']);
        $this->assertSame($expectedFeature['description'], $featureFound['description']);
        $this->assertSame($expectedFeature['canBeManageByAdmin'], $featureFound['canBeManageByAdmin']);
        $this->assertSame($expectedFeature['canBeManagedViaAPI'], $featureFound['canBeManagedViaAPI']);


        $secondFeature = $this->prepareRandomFeature('admin');

        $this->client->createFeature(
            $secondFeature['name'],
            $secondFeature['type'],
            $secondFeature['description']
        );

        $this->client->removeFeature($featureFound['id']);

        $this->assertSame(count($features), count($this->client->listFeatures()));
    }

    public function featureProvider(): Generator
    {
        $name = 'test-feature-' . $this->getRandomFeatureSuffix();
        yield 'global, canBeManageByAdmin:true, canBeManagedViaAPI:true' => [
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
        ];

        yield 'global, canBeManageByAdmin:false, canBeManagedViaAPI:false' => [
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
        ];

        yield 'global, canBeManageByAdmin:false, canBeManagedViaAPI:true' => [
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
        ];

        yield 'global, canBeManageByAdmin:true, canBeManagedViaAPI:false' => [
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
        ];
    }

    public function testFilterFeatures()
    {
        $expectedFeature = $this->prepareRandomFeature('project');

        $this->client->createFeature(
            $expectedFeature['name'],
            $expectedFeature['type'],
            $expectedFeature['description']
        );

        // try to find feature in wrong list
        $globalFeatures = $this->client->listFeatures(['type' => 'global']);
        $featureFoundInWrongList = false;
        foreach ($globalFeatures as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $featureFoundInWrongList = true;
                break;
            }
        }

        $this->assertFalse($featureFoundInWrongList);

        // find in correct list
        $projectFeatures = $this->client->listFeatures(['type' => 'project']);
        $foundFeature = null;

        foreach ($projectFeatures as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $foundFeature = $feature;
                break;
            }
        }

        $this->assertTrue($foundFeature !== null);
        $this->assertSame($expectedFeature['name'], $foundFeature['name']);
        $this->assertSame($expectedFeature['type'], $foundFeature['type']);
        $this->assertSame($expectedFeature['description'], $foundFeature['description']);
    }

    public function testFeatureDetail()
    {
        $newFeature = $this->prepareRandomFeature('admin');

        $insertedFeature = $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['description']
        );

        $fetchedFeature = $this->client->getFeature($insertedFeature['id']);

        $this->assertSame($newFeature['name'], $fetchedFeature['name']);
        $this->assertSame($newFeature['type'], $fetchedFeature['type']);
        $this->assertSame($newFeature['description'], $fetchedFeature['description']);
    }


    public function testFeatureDetailProjects()
    {
        $newFeature = $this->prepareRandomFeature('project');

        $insertedFeature = $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['description']
        );

        $fetchedFeature = $this->client->getFeature($insertedFeature['id']);

        $this->assertSame($newFeature['name'], $fetchedFeature['name']);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);
        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], $newFeature['name']);

        $featureProjects = $this->client->getFeatureProjects($insertedFeature['id']);

        $this->assertNotEmpty($featureProjects);
        $this->assertIsArray($featureProjects);

        $projectFound = null;

        foreach ($featureProjects as $featureProject) {
            if ($project['name'] === $featureProject['name']) {
                $projectFound = $featureProject;
                break;
            }
        }

        $this->assertTrue($projectFound !== null);
        $this->assertSame($project['id'], $projectFound['id']);
        $this->assertSame($project['name'], $projectFound['name']);
    }

    public function testFeatureDetailAdmins()
    {
        $newFeature = $this->prepareRandomFeature('admin');

        $insertedFeature = $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['description']
        );

        $fetchedFeature = $this->client->getFeature($insertedFeature['id']);

        $this->assertSame($newFeature['name'], $fetchedFeature['name']);

        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $userId = $token['user']['id'];
        $userEmail = $token['user']['email'];

        $this->client->addUserFeature($userId, $newFeature['name']);

        $featureAdmins = $this->client->getFeatureAdmins($insertedFeature['id']);

        $this->assertNotEmpty($featureAdmins);
        $this->assertIsArray($featureAdmins);

        $adminFound = null;

        foreach ($featureAdmins as $featureAdmin) {
            if ($userEmail === $featureAdmin['email']) {
                $adminFound = $featureAdmin;
                break;
            }
        }

        $this->assertTrue($adminFound !== null);
        $this->assertSame($userId, $adminFound['id']);
        $this->assertSame($userEmail, $adminFound['email']);
    }


    public function testCreateSameFeatureTwice()
    {
        $initialFeaturesCount = count($this->client->listFeatures());

        $newFeature = $this->prepareRandomFeature('admin');

        $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['description']
        );

        $this->assertSame($initialFeaturesCount + 1, count($this->client->listFeatures()));

        try {
            $this->client->createFeature(
                $newFeature['name'],
                $newFeature['type'],
                $newFeature['description']
            );
            $this->fail('Feature already exists');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        $this->assertSame($initialFeaturesCount + 1, count($this->client->listFeatures()));
    }

    public function testCreateFeatureWithWrongType()
    {
        $newFeature = $this->prepareRandomFeature('random-feature-type');

        try {
            $this->client->createFeature(
                $newFeature['name'],
                $newFeature['type'],
                $newFeature['description']
            );
            $this->fail('Invalid feature type');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }
    }

    public function testRemoveNonexistentFeature()
    {
        $features = $this->client->listFeatures();
        $lastFeature = end($features);
        try {
            $this->client->removeFeature($lastFeature['id'] + 1);
            $this->fail('Feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    private function prepareRandomFeature($type)
    {
        return [
            'name' => 'test-feature-' . $this->getRandomFeatureSuffix(),
            'type' => $type,
            'description' => 'test ' . $type . 'feature',
        ];
    }
}
