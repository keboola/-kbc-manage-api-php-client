<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use function GuzzleHttp\json_encode;

class FileStorageAbsTest extends ClientTestCase
{
    private const DEFAULT_ABS_OPTIONS = [
        'accountName' => TEST_ABS_ACCOUNT_NAME,
        'accountKey' => TEST_ABS_ACCOUNT_KEY,
        'containerName' => TEST_ABS_CONTAINER_NAME,
        'owner' => 'keboola',
    ];

    private const ROTATE_ABS_OPTIONS = [
        'accountKey' => TEST_ABS_ROTATE_ACCOUNT_KEY,
    ];

    private const ROTATE_S3_OPTIONS = [
        'awsKey' => TEST_S3_ROTATE_KEY,
        'awsSecret' => TEST_S3_ROTATE_SECRET,
    ];

    public function testFileStorageAbsCreate()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->assertArrayNotHasKey('accountKey', $storage);
        $this->assertSame($storage['accountName'], TEST_ABS_ACCOUNT_NAME);
        $this->assertSame($storage['containerName'], TEST_ABS_CONTAINER_NAME);
        $this->assertSame($storage['provider'], 'azure');
        $this->assertSame($storage['isDefault'], false);
    }

    public function testRotateAbsKey()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $rotatedStorage = $this->client->rotateAbsFileStorageCredentials($storage['id'], self::ROTATE_ABS_OPTIONS);
        $this->assertArrayNotHasKey('accountKey', $storage);
        $this->assertSame($rotatedStorage['accountName'], TEST_ABS_ACCOUNT_NAME);
        $this->assertSame($rotatedStorage['containerName'], TEST_ABS_CONTAINER_NAME);
        $this->assertSame($rotatedStorage['provider'], 'azure');
        $this->assertSame($rotatedStorage['isDefault'], false);
    }

    public function testListAbsStorages()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $initCount = count($this->client->listAbsFileStorage());
        $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);
        $storages = $this->client->listAbsFileStorage();

        $this->assertSame($initCount + 1, count($storages));

        foreach ($storages as $storage) {
            if ($storage['provider'] !== 'azure') {
                $this->fail('List of Azure Blob Storages contains also S3 Storage');
            }
        }
    }

    public function testSetAbsStorageAsDefault()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->assertFalse($storage['isDefault']);

        $storage = $this->client->setAbsFileStorageAsDefault($storage['id']);

        $this->assertTrue($storage['isDefault']);

        $storageList = $this->client->listAbsFileStorage();
        foreach ($storageList as $item) {
            if ($item['isDefault'] && $item['id'] !== $storage['id']) {
                $this->fail('There are more storage with default flag');
            }
        }
    }

    public function testCrossProviderStorageDefaultAbsS3()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('AWS S3 file storage "%d" not found', $storage['id']));
        $this->client->setS3FileStorageAsDefault($storage['id']);
    }

    public function testCreateAbsStorageWithoutRequiredParam()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(json_encode([
            "[accountName] : This field is missing.",
            "[accountKey] : This field is missing.",
            "[containerName] : This field is missing.",
            "[owner] : This field is missing.",
        ]));
        $this->client->createAbsFileStorage([]);
    }

    public function testRotateAbsCredentialsWithoutRequiredParams()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(json_encode([
            "[accountKey] : This field is missing."
        ]));

        $this->client->rotateAbsFileStorageCredentials($storage['id'], []);
    }

    public function testProjectAssignAbsFileStorage()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->client->assignFileStorage($project['id'], $storage['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($storage['id'], $project['fileStorage']['id']);
    }

    public function testCrossProviderStorageCredentialsRotateAbsS3()
    {
        if (!ENABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('AWS S3 file storage "%d" not found', $storage['id']));
        $this->client->rotateS3FileStorageCredentials($storage['id'], self::ROTATE_S3_OPTIONS);
    }
}