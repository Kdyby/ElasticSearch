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

	/**
	 * @param string $configFile
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer($configFile = 'default')
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(array('container' => array('class' => 'SystemContainer_' . md5($configFile))));
		Kdyby\ElasticSearch\DI\SearchExtension::register($config);
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		return $config->createContainer();
	}



	public function testFunctional()
	{
		$sl = $this->createContainer();

		Assert::type('Kdyby\ElasticSearch\Client', $sl->getService('search.elastica'));
		Assert::type('Elastica\Client', $sl->getService('search.elastica'));
		Assert::type('Kdyby\ElasticSearch\Diagnostics\Panel', $sl->getService('search.panel'));
	}

}

\run(new ExtensionTest());
