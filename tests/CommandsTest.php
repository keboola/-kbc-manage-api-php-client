<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

class CommandsTest extends ClientTestCase
{

    /**
     * @dataProvider  validParameters
     * @param $parameters
     */
    public function testSuperAdminShouldBeAllowedToRunCommand($parameters)
    {
        $response = $this->client->runCommand($parameters);
        $this->assertArrayHasKey('commandExecutionId', $response);
    }

    public function testNormalUserShouldNotBeAllowedToRunCommand()
    {
        try {
            $this->normalUserClient->runCommand([
                'command' => 'storage:workers-list',
                'parameters' => [
                    '--help',
                ],
            ]);
            $this->fail('Command should not be executed');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    /**
     * @dataProvider  invalidParameters
     * @param $parameters
     */
    public function testInvalidParameters($parameters)
    {
        try {
            $this->client->runCommand($parameters);
            $this->fail('Command should not be executed');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    public function validParameters()
    {
        return [
            [
                [
                    'command' => 'storage:workers-list',
                    'parameters' => [
                        '--help',
                    ],
                ]
            ],
            [
                [
                    'command' => 'storage:workers-list',
                ]
            ],
        ];
    }

    public function invalidParameters()
    {
        return [
            [
                [
                    'command' => 'test',
                    'parameters' => 'unknown',
                ]
            ],
        ];
    }
}
