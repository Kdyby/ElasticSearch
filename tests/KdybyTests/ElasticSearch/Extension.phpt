<?php

/**
 * Test: Kdyby\ElasticSearch\Extension.
 *
 * @testCase Kdyby\ElasticSearch\ExtensionTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\ElasticSearch
 */

namespace KdybyTests\ElasticSearch;

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	public function createContainer(string $configFile = 'default'): Nette\DI\Container
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5($configFile)]]);
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		$config->onCompile[] = static function ($config, Nette\DI\Compiler $compiler): void {
			$compiler->addExtension('elasticSearch', new Kdyby\ElasticSearch\DI\SearchExtension());
		};
		return $config->createContainer();
	}

	public function testFunctional(): void
	{
		$sl = $this->createContainer();

		Assert::type(Kdyby\ElasticSearch\Client::class, $sl->getService('elasticSearch.elastica'));
		Assert::type(\Elastica\Client::class, $sl->getService('elasticSearch.elastica'));
		Assert::type(Kdyby\ElasticSearch\Diagnostics\Panel::class, $sl->getService('elasticSearch.panel'));
	}

}

(new ExtensionTest())->run();
