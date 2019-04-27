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
use Nette;
use Nette\DI\Config;
use Nette\PhpGenerator as Code;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SearchExtension extends Nette\DI\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = [
		'debugger' => '%debugMode%',
	];

	/**
	 * @var array
	 */
	public $elasticaDefaults = [
		'connections' => [],
		'roundRobin' => FALSE,
		'retryOnConflict' => 0,
	];

	/**
	 * @var array
	 */
	public $connectionDefaults = [
		'host' => Elastica\Connection::DEFAULT_HOST,
		'port' => Elastica\Connection::DEFAULT_PORT,
		'path' => NULL,
		'proxy' => NULL,
		'transport' => Elastica\Connection::DEFAULT_TRANSPORT,
		'persistent' => TRUE,
		'timeout' => Elastica\Connection::TIMEOUT,
		'username' => NULL,
		'password' => NULL,
		'config' => [
			'curl' => [], # curl options
			'headers' => [], # additional curl headers
			'url' => NULL, # completely custom URL endpoint
		]
	];



	public function loadConfiguration()
	{
		/** @var array $config */
		$config = \Nette\DI\Config\Helpers::merge($this->getConfig(), $this->defaults + $this->elasticaDefaults);
		$this->setConfig($config);
		$builder = $this->getContainerBuilder();

		if (empty($config['connections'])) {
			$config['connections']['default'] = Config\Helpers::merge(array_intersect_key($config, $this->connectionDefaults), Nette\DI\Helpers::expand($this->connectionDefaults, $builder->parameters));

		} else {
			foreach ($config['connections'] as $name => $connectionConfig) {
				$config['connections'][$name] = Config\Helpers::merge($connectionConfig, Nette\DI\Helpers::expand($this->connectionDefaults, $builder->parameters));
			}
		}

		// replace curl string options with their CURLOPT_ constant values
		foreach ($config['connections'] as $name => $connectionConfig) {
			$curlOptions = [];
			foreach ($connectionConfig['config']['curl'] as $option => $value) {
				if (!defined($constant = 'CURLOPT_' . strtoupper($option))) {
					throw new Nette\InvalidArgumentException('There is no constant "' . $constant . '", therefore "' . $option . '" cannot be set.');
				}
				$curlOptions[constant($constant)] = $value;
			}
			$config['connections'][$name]['config']['curl'] = $curlOptions;
		}

		$elasticaConfig = array_intersect_key($config, $this->elasticaDefaults);
		$elastica = $builder->addDefinition($this->prefix('elastica'))
			->setFactory(Kdyby\ElasticSearch\Client::class, [$elasticaConfig]);

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('panel'))
				->setFactory(Kdyby\ElasticSearch\Diagnostics\Panel::class);

			$elastica->addSetup($this->prefix('@panel') . '::register', ['@self']);
		}
	}

	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->methods['initialize'];

		$debuggerClass = class_exists('Tracy\Debugger') ? 'Tracy\Debugger' : 'Nette\Diagnostics\Debugger';
		$initialize->addBody('?::getBlueScreen()->addPanel(?);', [new Code\PhpLiteral($debuggerClass), 'Kdyby\\ElasticSearch\\Diagnostics\\Panel::renderException']);
	}

}
