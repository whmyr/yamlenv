<?php
declare(strict_types = 1);

namespace Yamlenv;

use Composer\Autoload\ClassLoader;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    const INCLUDE_FILE = '/whmyr/yaml-include.php';

    /**
     * @var Composer
     */
    protected $composer;
    /**
     * @var IOInterface
     */
    protected $io;
    /**
     * @var Config
     */
    protected $config;

    /**
     * @return array
     */
    public static function getSubscribedEvents() : array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => ['onPreAutoloadDump']
        ];
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->config = Config::load($io, $composer->getConfig());
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function onPreAutoloadDump()
    {
        $this->io->write('<info>Processing yaml configuration to provide env vars!</info>');

        if (!class_exists(IncludeFile::class)) {
            // Plugin package was removed
            return;
        }
        $includeFilePath = $this->composer->getConfig()->get('vendor-dir') . self::INCLUDE_FILE;
        $includeFile = new IncludeFile($this->config, $this->createLoader(), $includeFilePath);
        if ($includeFile->dump()) {
            $rootPackage = $this->composer->getPackage();
            $autoloadDefinition = $rootPackage->getAutoload();
            $autoloadDefinition['files'][] = $includeFilePath;
            $rootPackage->setAutoload($autoloadDefinition);
            $this->io->writeError('<info>Registered whmyr/yamlenv</info>');
        } else {
            $this->io->writeError('<error>Could not dump whmyr/yamlenv autoload include file</error>');
        }
    }
    private function createLoader(): ClassLoader
    {
        $package = $this->composer->getPackage();
        $generator = $this->composer->getAutoloadGenerator();
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packageMap = $generator->buildPackageMap($this->composer->getInstallationManager(), $package, $packages);
        $map = $generator->parseAutoloads($packageMap, $package);
        return $generator->createLoader($map);
    }
}