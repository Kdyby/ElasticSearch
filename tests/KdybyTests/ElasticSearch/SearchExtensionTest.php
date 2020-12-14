<?php

namespace KdybyTests\ElasticSearch;

use Kdyby;
use Nette;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';


class SearchExtensionTest extends TestCase
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

		$this->assertInstanceOf(Kdyby\ElasticSearch\Client::class, $sl->getService('elasticSearch.elastica'));
		$this->assertInstanceOf(\Elastica\Client::class, $sl->getService('elasticSearch.elastica'));
		$this->assertInstanceOf(Kdyby\ElasticSearch\Diagnostics\Panel::class, $sl->getService('elasticSearch.panel'));
	}

}

(new SearchExtensionTest())->run();
