<?php
declare(strict_types = 1);

namespace Yamlenv;

use Composer\Autoload\ClassLoader;
use Composer\Util\Filesystem;


class IncludeFile
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ClassLoader
     */
    private $loader;

    /**
     * Absolute path to include file
     * @var string
     */
    private $includeFile;

    /**
     * Absolute path to include file template
     * @var string
     */
    private $includeFileTemplate;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        Config $config,
        $loader,
        $includeFile = '',
        $includeFileTemplate = '',
        Filesystem $filesystem = null
    ) {
        if (!$loader instanceof ClassLoader) {
            // We're called by a previous version of the plugin
            $includeFileTemplate = $includeFile;
            $includeFile = $loader;
            $loader = new ClassLoader();
        }
        $this->config = $config;
        $this->loader = $loader;
        $this->includeFile = $includeFile;
        $this->includeFileTemplate = $includeFileTemplate ?: dirname(__DIR__) . '/res/PHP/include.php.tmpl';
        $this->filesystem = $filesystem ?: new Filesystem();
    }
    public function dump()
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->includeFile));
        $successfullyWritten = false !== @file_put_contents($this->includeFile, $this->getIncludeFileContent());
        if ($successfullyWritten) {
            // Expose env vars of a possibly available .env file for following composer plugins
            $this->loader->register();
            require $this->includeFile;
            $this->loader->unregister();
        }
        return $successfullyWritten;
    }
    /**
     * Constructs the include file content
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return string
     */
    private function getIncludeFileContent()
    {
        $yamlFile = $this->config->get('yaml-file');
        $pathToYamlFileCode = $this->filesystem->findShortestPathCode(
            $this->includeFile,
            $yamlFile
        );
        $includeFileContent = file_get_contents($this->includeFileTemplate);
        $includeFileContent = $this->replaceToken('yaml-file', $pathToYamlFileCode, $includeFileContent);
        return $includeFileContent;
    }
    /**
     * Replaces a token in the subject (PHP code)
     *
     * @param string $name
     * @param string $content
     * @param string $subject
     * @return string
     */
    private function replaceToken($name, $content, $subject)
    {
        return str_replace('\'{$' . $name . '}\'', $content, $subject);
    }
}