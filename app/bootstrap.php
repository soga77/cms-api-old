<?php

declare(strict_types=1);

use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';

// Instantiate container
$container = new Container();

// Set up settings
$settings = require __DIR__ . '/settings.php';
$settings($container);

// Set up logger
$logger = require __DIR__ . '/logger.php';
$logger($container);

// Set up factories
$factories = require __DIR__ . '/factories.php';
$factories($container);

// Set up database
$rb = require __DIR__ . '/rb.php';
$rb($container);

// Set container on app
AppFactory::setContainer($container);

// Instantiate the app
$app = AppFactory::create();
//$app->setBasePath("");
$callableResolver = $app->getCallableResolver();
$responseFactory = $app->getResponseFactory();

$app->addBodyParsingMiddleware();

// Register middleware
$middleware = require __DIR__ . '/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/routes.php';
$routes($app);


/** @var bool $displayErrorDetails */
$errorSettings=  $app->getContainer()->get('settings')['error'];
$displayErrorDetails = $errorSettings['displayErrorDetails'];
$logErrors = $errorSettings['logErrors'];
$logErrorDetails = $errorSettings['logErrorDetails'];
$logger = $container->get(LoggerInterface::class);


// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler
$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

// Create Shutdown Handler
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);

// Add the routing middleware.
$app->addRoutingMiddleware();

// Add Error Handling Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails, $logger);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

$app->run();