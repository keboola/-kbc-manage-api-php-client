<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectTemplateFeaturesTest extends ClientTestCase
{
    const TEST_PROJECT_TEMPLATE_STRING_ID = 'productionRedshift';

    public function testListAddAndDeleteFeatures()
    {
        $featuresAtTheBeginning = $this->getFeatures();
        $randomFeature = $this->prepareRandomFeature();
        $this->createFeature($randomFeature);

        $this->client->addProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, $randomFeature['name']);

        $featuresAfterAdd = $this->getFeatures();

        $this->assertCount(count($featuresAtTheBeginning) + 1, $featuresAfterAdd);

        $featureFound = null;

        foreach ($featuresAfterAdd as $feature) {
            if (array_search($randomFeature['name'], $feature) !== false) {
                $featureFound = $feature;
                break;
            }
        }

        $this->assertNotNull($featureFound);
        $this->assertSame($randomFeature['name'], $featureFound['name']);
        $this->assertSame($randomFeature['type'], $featureFound['type']);
        $this->assertSame($randomFeature['description'], $featureFound['description']);

        $this->client->removeProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, $randomFeature['name']);

        $this->assertCount(count($featuresAtTheBeginning), $this->getFeatures());
    }

    public function testCreateSameFeatureTwice()
    {
        $featuresAtTheBeginning = $this->getFeatures();

        $randomFeature = $this->prepareRandomFeature();
        $this->createFeature($randomFeature);

        $this->client->addProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, $randomFeature['name']);
        $featuresAfterAdd = $this->getFeatures();

        $this->assertCount(count($featuresAtTheBeginning) + 1, $featuresAfterAdd);

        try {
            $this->client->addProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, $randomFeature['name']);
            $this->fail('Feature is already assigned to template');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        $this->assertCount(count($featuresAtTheBeginning) + 1, $this->getFeatures());
    }


    public function testRemoveNonexistentFeature()
    {
        try {
            $this->client->removeProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, 'random-feature-name-' . time());
            $this->fail('Template feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testAccessNonexistentTemplate()
    {
        try {
            $this->client->getProjectTemplateFeatures('random-template-name-' . time());
            $this->fail('Project template not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    private function prepareRandomFeature()
    {
        return [
            'name' => 'test-feature-project-template-' . uniqid('', true),
            'type' => 'project',
            'description' => 'project template feature',
        ];
    }

    private function createFeature($feature)
    {
        $this->client->createFeature($feature['name'], $feature['type'], $feature['description']);
    }

    private function getFeatures()
    {
        return $this->client->getProjectTemplateFeatures(self::TEST_PROJECT_TEMPLATE_STRING_ID);
    }
}
