<?php

namespace Johnbillion\WordPressExtension\Context\Initializer;

use Behat\Behat\Context\Context,
    Behat\Behat\Context\Initializer\ContextInitializer;

use Symfony\Component\Finder\Finder,
    Symfony\Component\Filesystem\Filesystem;

use Johnbillion\WordPressExtension\Context\WordPressContext;

/**
 * Class FeatureListener
 *
 * @package Johnbillion\WordPressExtension\Listener
 */
class WordPressContextInitializer implements ContextInitializer
{
    private $wordpressParams;
    private $minkParams;
    private $basePath;

    /**
     * inject the wordpress extension parameters and the mink parameters
     *
     * @param array  $wordpressParams
     * @param array  $minkParams
     * @param string $basePath
     */
    public function __construct($wordpressParams, $minkParams, $basePath)
    {
        $this->wordpressParams = $wordpressParams;
        $this->minkParams = $minkParams;
        $this->basePath = $basePath;
    }

    /**
     * setup the wordpress environment / stack if the context is a wordpress context
     *
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if (!$context instanceof WordPressContext) {
            return;
        }
        $this->prepareEnvironment();
        $this->installFileFixtures();
        $this->flushDatabase();
        $this->loadStack();
    }

    /**
     * prepare environment variables
     */
    private function prepareEnvironment()
    {
        // wordpress uses these superglobal fields everywhere...
        $urlComponents = parse_url($this->minkParams['base_url']);
        $_SERVER['HTTP_HOST'] = $urlComponents['host'] . (isset($urlComponents['port']) ? ':' . $urlComponents['port'] : '');
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';


        // we don't have a request uri in headless scenarios:
        // wordpress will try to "fix" php_self variable based on the request uri, if not present
        $PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';
    }

    /**
     * actually load the wordpress stack
     */
    private function loadStack()
    {
        // prevent wordpress from calling home to api.wordpress.org
        if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
            define('WP_INSTALLING', true);
        }

        $finder = new Finder();

        // load the wordpress "stack"
        $finder->files()->in($this->wordpressParams['path'])->depth('== 0')->name('wp-load.php');

        foreach ($finder as $bootstrapFile) {
            require_once $bootstrapFile->getRealpath();
        }
    }

    /**
     * create a wp-config.php and link plugins / themes
     */
    public function installFileFixtures()
    {
        $finder = new Finder();
        $fs = new Filesystem();
        $finder->files()->in($this->wordpressParams['path'])->depth('== 0')->name('wp-config-sample.php');
        foreach ($finder as $file) {
            $configContent =
                str_replace(array(
                    "'DB_NAME', 'database_name_here'",
                    "'DB_USER', 'username_here'",
                    "'DB_PASSWORD', 'password_here'"
                ), array(
                    sprintf("'DB_NAME', '%s'", $this->wordpressParams['connection']['db']),
                    sprintf("'DB_USER', '%s'", $this->wordpressParams['connection']['username']),
                    sprintf("'DB_PASSWORD', '%s'", $this->wordpressParams['connection']['password']),
                ), $file->getContents());
            $fs->dumpFile($file->getPath() . '/wp-config.php', $configContent);
        }

        if (isset($this->wordpressParams['symlink']['from']) && isset($this->wordpressParams['symlink']['to'])) {
            $from = $this->wordpressParams['symlink']['from'];

            if (substr($from, 0, 1) != '/') {
                $from = $this->basePath . DIRECTORY_SEPARATOR . $from;
            }
            if ($fs->exists($this->wordpressParams['symlink']['from'])) {
                $fs->symlink($from, $this->wordpressParams['symlink']['to'], true);
            }
        }
    }

    /**
     * flush the database if specified by flush_database parameter
     */
    public function flushDatabase()
    {
        if ($this->wordpressParams['flush_database']) {
            $connection = $this->wordpressParams['connection'];
            $mysqli = new \Mysqli(
                'localhost',
                $connection['username'],
                $connection['password'],
                $connection['db']
            );

            $result = $mysqli->multi_query("DROP DATABASE IF EXISTS ${connection['db']}; CREATE DATABASE ${connection['db']};");
        }
    }
}
