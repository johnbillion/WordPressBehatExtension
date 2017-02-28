<?php
namespace Johnbillion\WordPressExtension\Context;

use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;

/**
 * Class WordPressContext
 *
 * @package Johnbillion\WordPressExtension\Context
 */
class WordPressContext extends MinkContext
{
    /**
     * Create a new WordPress website from scratch
     *
     * @Given /^\w+ have|has a vanilla wordpress installation$/
     */
    public function installWordPress(TableNode $table = null)
    {
        global $wp_rewrite;

        if ( empty( $_SERVER['SERVER_NAME'] ) ) {
            $_SERVER['SERVER_NAME'] = 'example.com';
        }

        $name = "admin";
        $email = "an@example.com";
        $password = "test";
        $username = "admin";

        if ($table) {
            $hash = $table->getHash();
            $row = $hash[0];
            $name = $row["name"];
            $username = $row["username"];
            $email = $row["email"];
            $password = $row["password"];
        }

        $mysqli = new \Mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        $value = $mysqli->multi_query(implode("\n", array(
            "DROP DATABASE IF EXISTS " . DB_NAME . ";",
            "CREATE DATABASE " . DB_NAME . ";",
        )));
        assertTrue($value);
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        wp_install($name, $username, $email, true, '', $password);

        $wp_rewrite->init();
        $wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

    }

    /**
     * Add these users to this wordpress installation
     *
     * @see wp_insert_user
     *
     * @Given /^there are users$/
     */
    public function thereAreUsers(TableNode $table)
    {
        foreach ($table->getHash() as $userData) {
            if (!is_int(wp_insert_user($userData))) {
                throw new \InvalidArgumentException("Invalid user information schema.");
            }
        }
    }

    /**
     * Add these posts to this wordpress installation
     *
     * @see wp_insert_post
     *
     * @Given /^there are posts$/
     */
    public function thereArePosts(TableNode $table)
    {
        foreach ($table->getHash() as $postData) {
            if (!is_int(wp_insert_post($postData))) {
                throw new \InvalidArgumentException("Invalid post information schema.");
            }
        }
    }

    /**
     * Activate/Deactivate plugins
     * | plugin          | status  |
     * | plugin/name.php | enabled |
     *
     * @Given /^there are plugins$/
     */
    public function thereArePlugins(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            if ($row["status"] == "enabled") {
                activate_plugin( $row["plugin"] );
            } else {
                deactivate_plugins( $row["plugin"] );
            }
        }
    }


    /**
     * Login into the reserved area of this wordpress
     *
     * @Given /^I am logged in as "([^"]*)" with password "([^"]*)"$/
     */
    public function login($username, $password)
    {
    	$this->getSession()->reset();
        $this->visit("wp-login.php");
        $currentPage = $this->getSession()->getPage();

        $currentPage->fillField('user_login', $username);
        $currentPage->fillField('user_pass', $password);
        $currentPage->findButton('wp-submit')->click();

        assertTrue($this->getSession()->getPage()->hasContent('Dashboard'));
    }

}
