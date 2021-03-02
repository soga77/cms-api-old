<?php
declare(strict_types=1);

use DI\Container;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RedBeanPHP\R;

//require __DIR__ . '/rb-mysql.php';

return function (ContainerInterface $container) {
  $db = $container->get('settings')['db'];
  $connection = R::setup( 'mysql:host='.$db['host'].';dbname='.$db['dbname'],$db['username'], $db['password'] );
  return $connection;
};