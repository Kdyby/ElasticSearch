# Quickstart

This extension integrates the [ruflin/elastica](https://github.com/ruflin/Elastica) into Nette Framework.
For more information on how to use Elastica [read the official documentation](http://Elastica.io/).


## Installation

You can install the extension using this command

```sh
$ composer require kdyby/elastic-search
```

and enable the extension using your neon config.

```yml
extensions:
	elasticSearch: Kdyby\ElasticSearch\DI\SearchExtension
```


## Minimal configuration

Guess what, you don't have to configure anything! :)

But if you really really want to, you can use the following options, with the following default values

```yml
elasticSearch:
	host: localhost
	port: 9200
	path: NULL
	proxy: NULL
	transport: 'Http'
	persistent: on
	timeout: 300
	config:
		curl: [] # curl options
		headers: [] # additional curl headers
		url: [] # completely custom URL endpoint
	roundRobin: off
	retryOnConflict: 0
```

## Usage

The extension registers the `Kdyby\ElasticSearch\Client` as a service, which extends the `Elastica\Client` and adds custom logging for diagnostics panel. Simply inject the service and use it.

```php
class SearchManager
{
	/** @var \Kdyby\ElasticSearch\Client */
	private $elastica;

	public function __construct(\Kdyby\ElasticSearch\Client $elastica)
	{
		$this->elastica = $elastica;
	}

	// ...

```

