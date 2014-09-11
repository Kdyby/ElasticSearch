<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\ElasticSearch\Diagnostics;

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

	public $totalTime = 0;

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

		if ($this->queries) {
			$title->setText(
				count($this->queries) . ' call' . (count($this->queries) > 1 ? 's' : '') .
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



	public function success($client, $request, $response, $time)
	{
		$this->queries[] = [$request, $response, $time];
		$this->totalTime += $time;
	}



	public function failure($client, $request, $e, $time)
	{
		$response = method_exists($e, 'getResponse') ? $e->getResponse() : NULL;

		$this->queries[] = [$request, $response, $time, $e];
		$this->totalTime += $time;
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
