<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\ElasticSearch\Diagnostics;

use Elastica\Exception\ExceptionInterface;
use Elastica;
use Kdyby;
use Nette;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\IBarPanel;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Panel implements IBarPanel
{
    use Nette\SmartObject;

	/**
	 * @var float
	 */
	public $totalTime = 0;

	/**
	 * @var int
	 */
	public $queriesCount = 0;

	/**
	 * @var array
	 */
	public $queries = [];

	/**
	 * @var Kdyby\ElasticSearch\Client
	 */
	private $client;



	/**
	 * Renders HTML code for custom tab.
	 *
	 * @return string
	 */
	public function getTab()
	{
		$img = Html::el('')->addHtml(file_get_contents(__DIR__ . '/logo.svg'));
		$tab = Html::el('span')->title('ElasticSearch')->addHtml($img);
		$title = Html::el('span')->class('tracy-label');

		if ($this->queriesCount) {
			$title->setText(
				$this->queriesCount . ' call' . ($this->queriesCount > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime * 1000) . ' ms'
			);
		}

		return (string) $tab->addHtml($title);
	}



	/**
	 * @return string
	 */
	public function getPanel()
	{
		if (!$this->queries) {
			return NULL;
		}

		$esc = function ($s) {
			return htmlSpecialChars($s, ENT_QUOTES, 'UTF-8');
		};
		$click = class_exists('Tracy\Dumper')
			? function ($o, $c = FALSE, $d = 4) {
				return \Tracy\Dumper::toHtml($o, ['collapse' => $c, 'depth' => $d]);
			}
			: Nette\Utils\Callback::closure('Tracy\Helpers::clickableDump');
		$totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

		$extractData = function ($object) {
			if ($object instanceof Elastica\Request) {
				$data = $object->getData();

			} elseif ($object instanceof Elastica\Response) {
				$data = $object->getData();

			} else {
				return [];
			}

			try {
				return !is_array($data) ? Json::decode($data, Json::FORCE_ARRAY) : $data;

			} catch (Nette\Utils\JsonException $e) {
				try {
					return array_map(function($row) {
						return Json::decode($row, Json::FORCE_ARRAY);
					}, explode("\n", trim($data)));
				} catch (Nette\Utils\JsonException $e) {
					return $data;
				}
			}
		};

		$processedQueries = [];
		$allQueries = $this->queries;
		foreach ($allQueries as $authority => $requests) {
			/** @var Elastica\Request[] $item */
			foreach ($requests as $i => $item) {
				$processedQueries[$authority][$i] = $item;

				if (isset($item[3])) {
					continue; // exception, do not re-execute
				}

				if (stripos($item[0]->getPath(), '_search') === FALSE || $item[0]->getMethod() !== 'GET') {
					continue; // explain only search queries
				}

				if (!is_array($data = $extractData($item[0]))) {
					continue;
				}

				try {
					$response = $this->client->request(
						$item[0]->getPath(),
						$item[0]->getMethod(),
						$item[0]->getData(),
						['explain' => 1] + $item[0]->getQuery()
					);

					// replace the search response with the explained response
					$processedQueries[$authority][$i][1] = $response;

				} catch (\Exception $e) {
					// ignore
				}
			}
		}

		ob_start();

		require __DIR__ . '/panel.phtml';

		return ob_get_clean();
	}



	public function success($client, Elastica\Request $request, Elastica\Response $response, $time)
	{
		$this->queries[$this->requestAuthority($response)][] = [$request, $response, $time];
		$this->totalTime += $time;
		$this->queriesCount++;
	}



	public function failure($client, Elastica\Request $request, $e, $time)
	{
		/** @var Elastica\Response $response */
		$response = method_exists($e, 'getResponse') ? $e->getResponse() : NULL;

		$this->queries[$this->requestAuthority($response)][] = [$request, $response, $time, $e];
		$this->totalTime += $time;
		$this->queriesCount++;
	}



	protected function requestAuthority(Elastica\Response $response = NULL)
	{
		if ($response) {
			$info = $response->getTransferInfo();
			$url = new Nette\Http\Url($info['url']);

		} else {
			$url = new Nette\Http\Url(key($this->queries) ?: 'http://localhost:9200/');
		}

		return $url->hostUrl;
	}



	/**
	 * @param \Exception|\Throwable $e
	 * @return array|NULL
	 */
	public static function renderException($e = NULL)
	{
		if (!$e instanceof ExceptionInterface) {
			return NULL;
		}

		$panel = NULL;

		if ($e instanceof Elastica\Exception\ResponseException) {
			$panel .= '<h3>Request</h3>';
			$panel .= Dumper::toHtml($e->getRequest());

			$panel .= '<h3>Response</h3>';
			$panel .= Dumper::toHtml($e->getResponse());

		} elseif ($e instanceof Elastica\Exception\Bulk\ResponseException) {
			$panel .= '<h3>Failures</h3>';
			$panel .= Dumper::toHtml($e->getFailures());


		} /*elseif ($e->getQuery() !== NULL) {
			$panel .= '<h3>Query</h3>'
				. '<pre class="nette-dump"><span class="php-string">'
				. $e->getQuery()->getQuery()
				. '</span></pre>';
		} */

		return $panel ? [
			'tab' => 'ElasticSearch',
			'panel' => $panel
		] : NULL;
	}



	/**
	 * @param Kdyby\ElasticSearch\Client $client
	 */
	public function register(Kdyby\ElasticSearch\Client $client)
	{
		$this->client = $client;
		$client->onSuccess[] = [$this, 'success'];
		$client->onError[] = [$this, 'failure'];

		Debugger::getBar()->addPanel($this);
	}

}
