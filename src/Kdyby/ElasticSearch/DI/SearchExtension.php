<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\ElasticSearch\DI;

use Elastica;
use Kdyby;
use Kdyby\DoctrineCache\DI\Helpers as CacheHelpers;
use Nette;
use Nette\DI\Config;
use Nette\PhpGenerator as Code;



if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
	class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
	class_alias('Nette\Config\Helpers', 'Nette\DI\Config\Helpers');
}

if (isset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']) || !class_exists('Nette\Configurator')) {
	unset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']); // fuck you
	class_alias('Nette\Config\Configurator', 'Nette\Configurator');
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SearchExtension extends Nette\DI\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = array(
		'debugger' => '%debugMode%',
	);

	/**
	 * @var array
	 */
	public $elasticaDefaults = array(
		'connections' => array(),
		'roundRobin' => FALSE,
		'retryOnConflict' => 0,
	);

	/**
	 * @var array
	 */
	public $connectionDefaults = array(
		'host' => Elastica\Connection::DEFAULT_HOST,
		'port' => Elastica\Connection::DEFAULT_PORT,
		'path' => NULL,
		'proxy' => NULL,
		'transport' => Elastica\Connection::DEFAULT_TRANSPORT,
		'persistent' => TRUE,
		'timeout' => Elastica\Connection::TIMEOUT,
		'config' => array(
			'curl' => array(), # curl options
			'headers' => array(), # additional curl headers
			'url' => NULL, # completely custom URL endpoint
		)
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults + $this->elasticaDefaults);

		if (empty($config['connections'])) {
			$config['connections']['default'] = Config\Helpers::merge(array_intersect_key($config, $this->connectionDefaults), $builder->expand($this->connectionDefaults));

		} else {
			foreach ($config['connection'] as $name => $connectionConfig) {
				$config['connections'][$name] = Config\Helpers::merge($connectionConfig, $builder->expand($this->connectionDefaults));
			}
		}

		$elasticaConfig = array_intersect_key($config, $this->elasticaDefaults);
		$elastica = $builder->addDefinition($this->prefix('elastica'))
			->setClass('Kdyby\ElasticSearch\Client', array($elasticaConfig));

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('panel'))
				->setClass('Kdyby\ElasticSearch\Diagnostics\Panel');

			$elastica->addSetup($this->prefix('@panel') . '::register', array('@self'));
		}
	}



	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->methods['initialize'];

		$debuggerClass = class_exists('Tracy\Debugger') ? 'Tracy\Debugger' : 'Nette\Diagnostics\Debugger';
		$initialize->addBody('?::getBlueScreen()->addPanel(?);', array(new Code\PhpLiteral($debuggerClass), 'Kdyby\\ElasticSearch\\Diagnostics\\Panel::renderException'));
	}



	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('search', new SearchExtension());
		};
	}

}
