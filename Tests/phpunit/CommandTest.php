<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Output\StreamOutput;
use eZ\Bundle\EzPublishCoreBundle\Console\Application;

abstract class CommandTest extends WebTestCase
{
    protected $dslDir;
    protected $targetBundle = 'EzPublishCoreBundle'; // it is always present :-)
    protected $leftovers = array();

    /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
    private $_container;
    /** @var \eZ\Bundle\EzPublishCoreBundle\Console\Application $app */
    protected $app;
    /** @var StreamOutput $output */
    protected $output;

    // tell to phpunit not to mess with ezpublish legacy global vars...
    protected $backupGlobalsBlacklist = array('eZCurrentAccess');

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        // seems like this can not be used outside of the constructor...
        $this->dslDir = __DIR__ . '/../dsl';
    }

   /// @todo if we want to be compatible with phpunit >= 8.0, we should do something akin to https://github.com/symfony/framework-bundle/blob/4.3/Test/ForwardCompatTestTrait.php
    protected function setUp()
    {
        $this->_container = $this->bootContainer();

        $this->app = new Application(static::$kernel);
        $this->app->setAutoExit(false);
        $fp = fopen('php://temp', 'r+');
        $this->output = new StreamOutput($fp);
        $this->leftovers = array();
    }

    /**
     * Fetches the data from the output buffer, resetting it.
     * It would be nice to use BufferedOutput, but that is not available in Sf 2.3...
     * @return null|string
     */
    protected function fetchOutput()
    {
        if (!$this->output) {
            return null;
        }

        $fp = $this->output->getStream();
        rewind($fp);
        $out = stream_get_contents($fp);

        fclose($fp);
        $fp = fopen('php://temp', 'r+');
        $this->output = new StreamOutput($fp);

        return $out;
    }

    protected function tearDown()
    {
        foreach ($this->leftovers as $file) {
            unlink($file);
        }

        // clean buffer, just in case...
        if ($this->output) {
            $fp = $this->output->getStream();
            fclose($fp);
            $this->output = null;
        }

        // shuts down the kernel etc...
        parent::tearDown();
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     * @throws Exception
     */
    protected function bootContainer()
    {
        static::ensureKernelShutdown();

        if (!isset($_SERVER['SYMFONY_ENV'])) {
            throw new \Exception("Please define the environment variable SYMFONY_ENV to specify the environment to use for the tests");
        }
        // Run in our own test environment. Sf by default uses the 'test' one. We let phpunit.xml set it...
        // We also allow to disable debug mode
        $options = array(
            'environment' => $_SERVER['SYMFONY_ENV']
        );
        if (isset($_SERVER['SYMFONY_DEBUG'])) {
            $options['debug'] = $_SERVER['SYMFONY_DEBUG'];
        }
        try {
            static::bootKernel($options);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage() . " Did you forget to define the environment variable KERNEL_DIR?", $e->getCode(), $e->getPrevious());
        }

        // In Sf4 we do have the container available, in Sf3 we do not
        return isset(static::$container) ? static::$container : static::$kernel->getContainer();
    }

    protected function getContainer()
    {
        return $this->_container;
    }
}
