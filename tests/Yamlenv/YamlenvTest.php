<?php

use Yamlenv\Loader;
use Yamlenv\Validator;
use Yamlenv\Yamlenv;

class YamlenvTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var string
     */
    private $fixturesFolder;

    public function setUp() : void
    {
        $this->fixturesFolder = dirname(__DIR__) . '/fixtures/valid';
    }

    public function testYamlenvThrowsExceptionIfUnableToLoadFile()
    {
        $this->expectException(\Yamlenv\Exception\InvalidPathException::class);
        $this->expectExceptionMessage('Unable to read the environment file at');

        $yamlenv = new Yamlenv(__DIR__);
        $yamlenv->load();
    }

    public function testYamlenvLoadsEnvironmentVars()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $this->assertSame('bar', getenv('FOO'));
        $this->assertSame('baz', getenv('BAR'));
        $this->assertSame('with spaces', getenv('SPACED'));
        $this->assertEmpty(getenv('EMPTY'));
    }

    public function testCommentedYamlenvLoadsEnvironmentVars()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'commented.yml');
        $yamlenv->load();
        $this->assertSame('bar', getenv('CFOO'));
        $this->assertFalse(getenv('CBAR'));
        $this->assertFalse(getenv('CZOO'));
        $this->assertSame('with spaces', getenv('CSPACED'));
        $this->assertSame('a value with a # character', getenv('CQUOTES'));
        $this->assertSame('a value with a # character & a quote " character inside quotes', getenv('CQUOTESWITHQUOTE'));
        $this->assertEmpty(getenv('CNULL'));
    }

    public function testQuotedYamlenvLoadsEnvironmentVars()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'quoted.yml');
        $yamlenv->load();
        $this->assertSame('bar', getenv('QFOO'));
        $this->assertSame('baz', getenv('QBAR'));
        $this->assertSame('with spaces', getenv('QSPACED'));
        $this->assertEmpty(getenv('QNULL'));
        $this->assertSame('pgsql:host=localhost;dbname=test', getenv('QEQUALS'));
        $this->assertSame('test some escaped characters like a quote (") or maybe a backslash (\\)', getenv('QESCAPED'));
    }

    public function testSpacedValuesWithoutQuotesThrowsException()
    {
        $this->expectException(\Yamlenv\Exception\InvalidFileException::class);
        $this->expectExceptionMessage('Input file does not contain valid Yaml');

        $yamlenv = new Yamlenv(dirname(__DIR__) . '/fixtures/invalid', 'invalid.yml');
        $yamlenv->load();
    }

    public function testYamlenvLoadsEnvGlobals()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $this->assertSame('bar', $_SERVER['FOO']);
        $this->assertSame('baz', $_SERVER['BAR']);
        $this->assertSame('with spaces', $_SERVER['SPACED']);
        $this->assertEmpty($_SERVER['EMPTY']);
    }

    public function testYamlenvLoadsServerGlobals()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $this->assertSame('bar', $_ENV['FOO']);
        $this->assertSame('baz', $_ENV['BAR']);
        $this->assertSame('with spaces', $_ENV['SPACED']);
        $this->assertEmpty($_ENV['EMPTY']);
    }

    /**
     * @depends testYamlenvLoadsEnvironmentVars
     * @depends testYamlenvLoadsEnvGlobals
     * @depends testYamlenvLoadsServerGlobals
     */
    public function testYamlenvRequiredStringEnvironmentVars()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $validator = $yamlenv->required('FOO');

        $this->assertInstanceOf(Validator::class, $validator);
    }

    /**
     * @depends testYamlenvLoadsEnvironmentVars
     * @depends testYamlenvLoadsEnvGlobals
     * @depends testYamlenvLoadsServerGlobals
     */
    public function testYamlenvRequiredArrayEnvironmentVars()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $validator = $yamlenv->required(['FOO', 'BAR']);

        $this->assertInstanceOf(Validator::class, $validator);
    }

    /**
     * @depends testYamlenvLoadsEnvironmentVars
     * @depends testYamlenvLoadsEnvGlobals
     * @depends testYamlenvLoadsServerGlobals
     */
    public function testYamlenvRequiredIntegerEnvironmentVar()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: FOO is not an integer.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();

        $yamlenv->required(['FOO'])->isInteger();
    }

    public function testYamlenvNestedEnvironmentVars()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'nested.yml');
        $yamlenv->load();

        $this->assertSame('Hello', $_ENV['NVAR1']);
        $this->assertSame('World!', $_ENV['NVAR2']);
        $this->assertSame('Nested 1', $_ENV['NVAR3_NVAR4']);
        $this->assertSame('Nested 2', $_ENV['NVAR3_NVAR5_NVAR6']);
    }

    /**
     * @depends testYamlenvLoadsEnvironmentVars
     * @depends testYamlenvLoadsEnvGlobals
     * @depends testYamlenvLoadsServerGlobals
     */
    public function testYamlenvAllowedValues()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $validator = $yamlenv->required('FOO')->allowedValues(['bar', 'baz']);

        $this->assertInstanceOf(Validator::class, $validator);
    }

    /**
     * @depends testYamlenvLoadsEnvironmentVars
     * @depends testYamlenvLoadsEnvGlobals
     * @depends testYamlenvLoadsServerGlobals
     */
    public function testYamlenvProhibitedValues()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: FOO is not an allowed value.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $yamlenv->required('FOO')->allowedValues(['buzz']);
    }

    public function testYamlenvRequiredThrowsRuntimeException()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: FOOX is missing, NOPE is missing.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();
        $this->assertFalse(getenv('FOOX'));
        $this->assertFalse(getenv('NOPE'));
        $yamlenv->required(['FOOX', 'NOPE']);
    }

    public function testYamlenvNullFileArgumentUsesDefault()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, null);
        $yamlenv->load();
        $this->assertSame('bar', getenv('FOO'));
    }

    /**
     * The fixture data has whitespace between the key and in the value string.
     *
     * Test that these keys are trimmed down.
     */
    public function testYamlenvTrimmedKeys()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'quoted.yml');
        $yamlenv->load();
        $this->assertSame('no space', getenv('QWHITESPACE'));
    }

    public function testYamlenvLoadDoesNotOverwriteEnv()
    {
        $this->clearEnv();

        putenv('IMMUTABLE=true');
        $yamlenv = new Yamlenv($this->fixturesFolder, 'immutable.yml');
        $yamlenv->load();

        $this->assertSame('true', getenv('IMMUTABLE'));
    }

    public function testYamlenvLoadAfterOverload()
    {
        $this->clearEnv();

        putenv('IMMUTABLE=true');
        $yamlenv = new Yamlenv($this->fixturesFolder, 'immutable.yml');
        $yamlenv->overload();
        $this->assertSame('false', getenv('IMMUTABLE'));

        putenv('IMMUTABLE=true');
        $yamlenv->load();
        $this->assertSame('true', getenv('IMMUTABLE'));
    }

    public function testYamlenvOverloadAfterLoad()
    {
        $this->clearEnv();

        putenv('IMMUTABLE=true');
        $yamlenv = new Yamlenv($this->fixturesFolder, 'immutable.yml');
        $yamlenv->load();
        $this->assertSame('true', getenv('IMMUTABLE'));

        putenv('IMMUTABLE=true');
        $yamlenv->overload();
        $this->assertSame('false', getenv('IMMUTABLE'));
    }

    public function testYamlenvOverloadDoesOverwriteEnv()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'mutable.yml');
        $yamlenv->overload();
        $this->assertSame('true', getenv('MUTABLE'));
    }

    public function testYamlenvAllowsSpecialCharacters()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'specialchars.yml');
        $yamlenv->load();
        $this->assertSame('$a6^C7k%zs+e^.jvjXk', getenv('SPVAR1'));
        $this->assertSame('?BUty3koaV3%GA*hMAwH}B', getenv('SPVAR2'));
        $this->assertSame('jdgEB4{QgEC]HL))&GcXxokB+wqoN+j>xkV7K?m$r', getenv('SPVAR3'));
        $this->assertSame('22222:22#2^{', getenv('SPVAR4'));
        $this->assertSame('test some escaped characters like a quote " or maybe a backslash \\', getenv('SPVAR5'));
    }

    public function testYamlenvConvertsToUppercase()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'lowercase.yml', true);
        $yamlenv->load();

        $validator = $yamlenv->required([
            'LCVAR1',
            'LCVAR2',
            'LCVAR3',
        ])->notEmpty();

        $this->assertInstanceOf(Validator::class, $validator);
    }

    public function testYamlenvFailsIfNotConvertedToUppercase()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: LCVAR1 is missing, '
            . 'LCVAR2 is missing, LCVAR3 is missing.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'lowercase.yml', false);
        $yamlenv->load();

        $yamlenv->required([
            'LCVAR1',
            'LCVAR2',
            'LCVAR3',
        ]);
    }

    public function testYamlenvAssertions()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'assertions.yml');
        $yamlenv->load();
        $this->assertSame('val1', getenv('ASSERTVAR1'));
        $this->assertEmpty(getenv('ASSERTVAR2'));
        $this->assertEmpty(getenv('ASSERTVAR3'));
        $this->assertSame('0', getenv('ASSERTVAR4'));

        $validator = $yamlenv->required([
            'ASSERTVAR1',
            'ASSERTVAR2',
            'ASSERTVAR3',
            'ASSERTVAR4',
        ]);

        $this->assertInstanceOf(Validator::class, $validator);

        $validator = $yamlenv->required([
            'ASSERTVAR1',
            'ASSERTVAR4',
        ])->notEmpty();

        $this->assertInstanceOf(Validator::class, $validator);

        $validator = $yamlenv->required([
            'ASSERTVAR1',
            'ASSERTVAR4',
        ])->notEmpty()->allowedValues(['0', 'val1']);

        $this->assertInstanceOf(Validator::class, $validator);
    }

    public function testYamlenvEmptyThrowsRuntimeException()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: ASSERTVAR2 is empty.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'assertions.yml');
        $yamlenv->load();
        $this->assertEmpty(getenv('ASSERTVAR2'));

        $yamlenv->required('ASSERTVAR2')->notEmpty();
    }

    public function testYamlenvStringOfSpacesConsideredEmpty()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: ASSERTVAR3 is empty.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'assertions.yml');
        $yamlenv->load();
        $this->assertEmpty(getenv('ASSERTVAR3'));

        $yamlenv->required('ASSERTVAR3')->notEmpty();
    }

    public function testYamlenvHitsLastChain()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: ASSERTVAR3 is empty.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'assertions.yml');
        $yamlenv->load();
        $yamlenv->required('ASSERTVAR3')->notEmpty();
    }

    public function testYamlenvValidateRequiredWithoutLoading()
    {
        $this->expectException(\Yamlenv\Exception\ValidationException::class);
        $this->expectExceptionMessage(
            'One or more environment variables failed assertions: foo is missing.'
        );

        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'assertions.yml');
        $yamlenv->required('foo');
    }

    public function testYamlenvRequiredCanBeUsedWithoutLoadingFile()
    {
        $this->clearEnv();

        putenv('REQUIRED_VAR=1');
        $yamlenv = new Yamlenv($this->fixturesFolder);
        $validator = $yamlenv->required('REQUIRED_VAR')->notEmpty();

        $this->assertInstanceOf(Validator::class, $validator);
    }

    public function testGetLoaderGetsLoaderInstanceAfterLoad()
    {
        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->load();

        $loader = $yamlenv->getLoader();

        $this->assertInstanceOf(Loader::class, $loader);
    }

    public function testGetLoaderGivesNullBeforeLoad()
    {
        $this->expectException(\Yamlenv\Exception\LoaderException::class);
        $this->expectExceptionMessage('Loader has not been initialized yet.');

        $yamlenv = new Yamlenv($this->fixturesFolder);
        $yamlenv->getLoader();
    }

    public function testGetEnvReturnsTheEnvValue()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'env.yml');
        $yamlenv->load();

        $expected = 'bar';

        $actual = $yamlenv->getEnv('FOO');

        $this->assertEquals($expected, $actual);
    }

    public function testGetRawEnvReturnsTheYamlValue()
    {
        $this->clearEnv();

        $yamlenv = new Yamlenv($this->fixturesFolder, 'env.yml');
        $yamlenv->load();

        $expected = [
            'ARRAY_ONE' => 1,
            'ARRAY_TWO' => 2,
        ];

        $actual = $yamlenv->getRawEnv('NESTED');

        $this->assertEquals($expected, $actual);
    }

    /**
     * Clear all env vars.
     */
    private function clearEnv()
    {
        foreach ($_ENV as $key => $value) {
            $this->clearEnvironmentVariable($key);
        }
    }

    /**
     * @param $name
     */
    private function clearEnvironmentVariable($name)
    {
        if (function_exists('putenv')) {
            putenv($name);
        }

        unset($_ENV[$name], $_SERVER[$name]);
    }
}
