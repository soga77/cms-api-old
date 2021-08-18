<?php
declare(strict_types=1);

use DI\Container;
use Psr\Container\ContainerInterface;
use App\Controllers\EmailTemplateController;
use App\Controllers\ModuleController;
use App\Controllers\PageController;
use App\Controllers\BlockController;
use App\Controllers\UserAuth;
use App\Controllers\UserController;
use App\Controllers\PermissionController;
use App\Controllers\NotificationController;
use App\Controllers\RoleController;

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
	$container->set('BlockController', function (ContainerInterface $c) {
		return new BlockController($c);
	});
	$container->set('UserAuth', function (ContainerInterface $c) {
		return new UserAuth($c);
	});
	$container->set('UserController', function (ContainerInterface $c) {
		return new UserController($c);
	});
	$container->set('PermissionController', function (ContainerInterface $c) {
		return new PermissionController($c);
	});
	$container->set('NotificationController', function (ContainerInterface $c) {
		return new NotificationController($c);
	});	
	$container->set('RoleController', function (ContainerInterface $c) {
		return new RoleController($c);
	});
	
};