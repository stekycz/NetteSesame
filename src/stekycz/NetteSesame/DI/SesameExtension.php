<?php

namespace stekycz\NetteSesame\DI;

use Nette;
use Nette\Utils\Validators;



if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
	class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
}

if (isset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']) || !class_exists('Nette\Configurator')) {
	unset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']); // fuck you
	class_alias('Nette\Config\Configurator', 'Nette\Configurator');
}

class SesameExtension extends Nette\DI\CompilerExtension
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
	 * @param \Nette\Configurator $configurator
	 */
	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('sesame', new self());
		};
	}

}
