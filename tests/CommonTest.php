<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

class CommonTest extends ClientTestCase
{

	public function testVerifyToken()
	{
		$token = $this->client->verifyToken();
	}
}