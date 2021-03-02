<?php
declare(strict_types=1);

use DI\Container;
use Psr\Container\ContainerInterface;
use App\Controllers\EmailTemplateController;
use App\Controllers\ModuleController;
use App\Controllers\PageController;
use App\Controllers\UserController;

return function (Container $container) {
	$container->set('EmailTemplateController', function (ContainerInterface $c) {
		return new EmailTemplateController($c);
	});
	$container->set('ModuleController', function (ContainerInterface $c) {
		return new ModuleController($c);
	});
	$container->set('PageController', function (ContainerInterface $c) {
		return new PageController($c);
	});
	$container->set('UserController', function (ContainerInterface $c) {
		return new UserController($c);
	});
};