<?php
/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

// Our configuration should come from Tigerdile.
// Let's see if we have a symlink

$root = dirname(dirname(__DIR__));

if((!is_link("{$root}/tigerdile")) && (!is_dir("{$root}/tigerdile"))) {
    echo "Tigerdile link is not set up.";
    exit;
}

// Short circuit the requirement inside wp-config
define('CHAT_APP', 1);
require_once("{$root}/tigerdile/wp-config.php");

return array(
    'db' => array(
            'driver' => 'Pdo',
            'dsn' => 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';charset=' . DB_CHARSET,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
    ),
    'service_manager' => array(
            'factories' => array(
                    'Zend\Db\Adapter\Adapter' => 'Zend\Db\Adapter\AdapterServiceFactory'
            )
    ),
    'php_settings' => array(
            'date.timezone' => 'America/Chicago',
            'auto_detect_line_endings' => '1',
            'pcre.backtrack_limit' => 100000000,
    ),
    'media' => array(
            'publicMediaBasePath' => "{$root}/public/uploads",
            'privateMediaBasePath' => "{$root}/private/uploads",
    ),

    // Swaggerdile used to interface with WordPress before Swaggerdile became Tigerdile
    // This isn't used anymore.  They still need to be defined, but maybe we should
    // get rid of this.
    'wordpress_api_key' => 'xxx',
    'wordpress_api_url' => 'https://gone/',

    // Test mail configuration
    'mail' => array(
        'transport' => '\Zend\Mail\Transport\InMemory',
        'smtpOptions' => null,
        'sendFrom' => 'support@tigerdile.com',
        'sendFromUser' => 'Tigerdile Support',
    ),

    // Crypto config
    // This isn't used anymore -- used to be we secured all
    // private tax documents in a secure vault server.  This
    // server doesn't even exist anymore and those files have
    // been purged.
    'crypto' => array(
        'publicKey' => "{$root}/config/swaggerdile.public",
        'repoServerUrl' => 'http://gone/',
    ),

    // Log config
    'log' => array('logger' => array(
        'writers' => array(
            'stream' => array(
                'name' => 'stream',
                'options' => array(
                    'stream' => "{$root}/logs/" . @date('Y-m-d') . '.txt',
                )
            )
        ))
    ),
    'stripe_secret_key' => TIGERDILE_STRIPE_SECRET_KEY,
    'stripe_publish_key' => TIGERDILE_STRIPE_PUBLISH_KEY,
    'cookiebase_name' => COOKIEBASE_NAME,
    'cookiebase_short_secret' => COOKIEBASE_SHORT_SECRET,
    'cookiebase_secret' => COOKIEBASE_SECRET,
    'cookiebase_domain' => '.tigerdile.com',
    'cookiebase_timeout' => 31536000,
);
