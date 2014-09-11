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
use Tracy\Bar;
use Tracy\BlueScreen;
use Tracy\Debugger;
use Tracy\IBarPanel;



if (!class_exists('Tracy\Debugger')) {
	class_alias('Nette\Diagnostics\Debugger', 'Tracy\Debugger');
}


if (!class_exists('Tracy\Bar')) {
	class_alias('Nette\Diagnostics\Bar', 'Tracy\Bar');
	class_alias('Nette\Diagnostics\BlueScreen', 'Tracy\BlueScreen');
	class_alias('Nette\Diagnostics\Helpers', 'Tracy\Helpers');
	class_alias('Nette\Diagnostics\IBarPanel', 'Tracy\IBarPanel');
}

if (!class_exists('Tracy\Dumper')) {
	class_alias('Nette\Diagnostics\Dumper', 'Tracy\Dumper');
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Panel extends Nette\Object implements IBarPanel
{

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
	public $queries = array();



	/**
	 * Renders HTML code for custom tab.
	 *
	 * @return string
	 */
	public function getTab()
	{
		$img = Html::el('img', array('height' => '16px'))
			->src('data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/logo.png')));
		$tab = Html::el('span')->title('ElasticSearch')->add($img);
		$title = Html::el()->setText('ElasticSearch');

		if ($this->queriesCount) {
			$title->setText(
				$this->queriesCount . ' call' . ($this->queriesCount > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime * 1000) . ' ms'
			);
		}

		return (string) $tab->add($title);
	}



	/**
	 * @return string
	 */
	public function getPanel()
	{
		if (!$this->queries) {
			return NULL;
		}

		ob_start();
		$esc = callback('Nette\Templating\Helpers::escapeHtml');
		$click = class_exists('\Tracy\Dumper')
			? function ($o, $c = FALSE) {
				return \Tracy\Dumper::toHtml($o, array('collapse' => $c));
			}
			: callback('\Tracy\Helpers::clickableDump');
		$totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

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
	 * @param Kdyby\ElasticSearch\Client $client
	 */
	public function register(Kdyby\ElasticSearch\Client $client)
	{
		$client->onSuccess[] = $this->success;
		$client->onError[] = $this->failure;

		self::getDebuggerBar()->addPanel($this);
		// self::getDebuggerBlueScreen()->addPanel(array($this, 'renderException'));
	}



	/**
	 * @return Bar
	 */
	private static function getDebuggerBar()
	{
		return method_exists('Tracy\Debugger', 'getBar') ? Debugger::getBar() : Debugger::$bar;
	}



	/**
	 * @return BlueScreen
	 */
	private static function getDebuggerBlueScreen()
	{
		return method_exists('Tracy\Debugger', 'getBlueScreen') ? Debugger::getBlueScreen() : Debugger::$blueScreen;
	}

}
