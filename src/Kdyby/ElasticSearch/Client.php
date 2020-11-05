<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\ElasticSearch;

use Elastica;
use Elastica\Request;
use Elastica\Response;
use Kdyby;
use Nette;
use Nette\Utils\ObjectMixin;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Client extends Elastica\Client
{

	use Nette\SmartObject;

	public $onSuccess = [];
	public $onError = [];


	/**
	 * @param string $path
	 * @param string $method
	 * @param array $data
	 * @param array $query
	 * @param string $contentType
	 * @return Response
	 * @throws \Exception
	 */
    public function request(string $path, string $method = Request::GET, $data = [], array $query = [], string $contentType = Request::DEFAULT_CONTENT_TYPE): Response
	{
		$begin = microtime(TRUE);

		try {
			$response = parent::request($path, $method, $data, $query, $contentType);
			$this->onSuccess($this, $this->_lastRequest, $response, microtime(TRUE) - $begin);

			return $response;

		} catch (\Exception $e) {
			$this->onError($this, $this->_lastRequest, $e, microtime(TRUE) - $begin);
			throw $e;
		}
	}

}
