<?php

namespace stekycz\NetteSesame\DI;

use Nette\Config\Compiler;
use Nette\Config\CompilerExtension;
use Nette\Config\Configurator;
use Nette\Utils\Validators;

if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
	class_alias('Nette\Config\Configurator', 'Nette\Configurator');
	class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
}



class SesameExtension extends CompilerExtension
{

	/**
	 * @var string[]
	 */
	public $defaults = array(
		'dsl' => 'http://localhost:8080/openrdf-sesame',
		'repository' => NULL,
	);



	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults);
		Validators::assert($config['dsl'], 'url', 'Sesame connection string (DSL)');
		Validators::assert($config['repository'], 'string|null', 'Sesame repository name');

		$container->addDefinition($this->prefix('client'))
			->setClass('stekycz\NetteSesame\SesameClient', array(
				$config['dsl'],
				$config['repository'],
			));
	}



	/**
	 * @param \Nette\Config\Configurator $configurator
	 */
	public static function register(Configurator $configurator)
	{
		$configurator->onCompile[] = function (Configurator $config, Compiler $compiler) {
			$compiler->addExtension('sesame', new self());
		};
	}

}
