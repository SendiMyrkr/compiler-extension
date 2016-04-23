<?php

namespace Mrtnzlml\Tests;

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

/**
 * @testCase
 */
class CompilerExtension extends \Tester\TestCase
{

	/** @var CustomExtension */
	private $extension;

	/** @var \Nette\PhpGenerator\ClassType */
	private $generatedContainer;

	public function setUp()
	{
		$compiler = new \Nette\DI\Compiler;
		$this->extension = (new CustomExtension)->setCompiler($compiler, 'ext');

		$compiler->addExtension('extensions', new \Nette\DI\Extensions\ExtensionsExtension);
		$compiler->addExtension('latte', new \Nette\Bridges\ApplicationDI\LatteExtension(dirname(__DIR__) . '/temp'));
		$compiler->addExtension('application', new \Nette\Bridges\ApplicationDI\ApplicationExtension);
		$compiler->addExtension('routing', new \Nette\Bridges\ApplicationDI\RoutingExtension);
		$compiler->addExtension('http', new \Nette\Bridges\HttpDI\HttpExtension);
		$compiler->addExtension('ext1', $this->extension);

		$compiler->loadConfig(__DIR__ . '/config.neon');
		$this->generatedContainer = $compiler->compile()[0];
	}

	public function testAddConfigParameters()
	{
		$builder = $this->extension->getContainerBuilder();
		Assert::same([
			'k1' => 'v1',
			'k2' => 'overridden',
			'k3' => 'v3',
		], $builder->parameters);
	}

	public function testAddConfigExtensions()
	{
		Assert::same([
			'extensions' => 'Nette\\DI\\Extensions\\ExtensionsExtension',
			'latte' => 'Nette\\Bridges\\ApplicationDI\\LatteExtension',
			'application' => 'Nette\\Bridges\\ApplicationDI\\ApplicationExtension',
			'routing' => 'Nette\\Bridges\\ApplicationDI\\RoutingExtension',
			'http' => 'Nette\\Bridges\\HttpDI\\HttpExtension',
			'ext1' => 'Mrtnzlml\\Tests\\CustomExtension',
			'ext2' => 'Mrtnzlml\\Tests\\CustomExtension2',
		], array_map(function ($item) {
			return get_class($item);
		}, $this->extension->getExtensions()));

		/** @var CustomExtension2 $extension */
		$extension = $this->extension->getExtensions('Mrtnzlml\Tests\CustomExtension2')['ext2'];
		Assert::same([
			'ek1' => 'ev1',
			'ek2' => 'overridden',
			'ek3' => 'ev3',
		], $extension->getConfig());
	}

	public function testAddConfigServices()
	{
		$builder = $this->extension->getContainerBuilder();
		Assert::same([
			'application.application',
			'application.presenterFactory',
			'application.linkGenerator',
			'routing.router',
			'http.requestFactory',
			'http.request',
			'http.response',
			'http.context',
			'9_Mrtnzlml_Tests_TestService',
			'named',
			'11_Mrtnzlml_Tests_DefaultService',
			'application.1',
			'application.2',
			'container',
		], array_keys($builder->getDefinitions()));

		Assert::same('Mrtnzlml\Tests\TestService', $builder->getDefinition('9_Mrtnzlml_Tests_TestService')->getFactory()->getEntity());
		Assert::same('Mrtnzlml\Tests\Service', $builder->getDefinition('named')->getFactory()->getEntity());
	}

	public function testSetMapping()
	{
		$method = $this->generatedContainer->getMethod(\Nette\DI\Container::getMethodName('application.presenterFactory'));
		$expectedBody = <<<'BODY'
$service = new Nette\Application\PresenterFactory(new Nette\Bridges\ApplicationDI\PresenterFactoryCallback($this, 1, NULL));
$service->setMapping(array('*' => '*'));
$service->setMapping(array(
	'Module' => 'App\*Module\Controllers\*Controller',
));
return $service;
BODY;
		Assert::same($expectedBody, $method->getBody());
	}

	public function testReloadDefinition()
	{
		Assert::exception(function () {
			$this->extension->reloadDefinition(1);
		}, \Nette\InvalidArgumentException::class, 'Definition regex should be string name or array od string names.');
	}

}

(new CompilerExtension)->run();