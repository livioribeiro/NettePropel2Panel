<?php

// must be included from bootstrap.php 

use Nette;
use Nette\Diagnostics\Debugger;
use Nette\Utils\Neon;

use Monolog\Logger;

use Propel\Runtime\Propel;
use Propel\Runtime\Connection\ConnectionManagerSingle;

Use Addons\Diagnostics\PropelPanel;

$settings = Neon::decode(file_get_contents(__DIR__ . '/config/config.local.neon'));
$db = $settings['parameters']['propel'];

$serviceContainer = Propel::getServiceContainer();
$serviceContainer->setAdapterClass($db['datasource'], $db['adapter']);

$config = array(
    'dsn'      => "$db[adapter]:host=$db[host];dbname=$db[dbname]",
    'user'     => $db['user'],
    'password' => $db['password']
);

if (Nette\Configurator::detectDebugMode()) {
    $config['classname'] = 'Propel\Runtime\Connection\ProfilerConnectionWrapper';
}

$manager = new ConnectionManagerSingle();
$manager->setConfiguration($config);
$serviceContainer->setConnectionManager($db['datasource'], $manager);

$panel = new PropelPanel();

$logger = new Logger('defaultLogger');
$logger->pushHandler($panel);
$serviceContainer->setLogger('defaultLogger', $logger);

Debugger::addPanel($panel);
