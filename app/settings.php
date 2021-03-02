<?php
declare(strict_types=1);


use DI\Container;
use Monolog\Logger;

return function (Container $container) {
	$container->set("settings", function() {
		return [
			"error" => [
			"logErrorDetails" => false,
			"displayErrorDetails" => true,
			"logErrors" => true,
			],			
			"logger" => [
				"name" => "slim-app",
				"path" =>  __DIR__ . "/../logs/app.log",
				"level" => Logger::DEBUG,
			],
			"db" => [
				"port" => 3306,
				"host" => "localhost",
				"dbname" => "svdb",
				"username" => "root",
				"password" => "brandon99"
			],
			"mailer" => [
        "smtpDebug" => true,
        "host" => "smtp.sendgrid.net",
        "smtpAuth" => true,
        "username" => "apikey",
        "password" => "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",
        "fromEmail" => "jibodu@outlook.com",
        "fromName" => "David Jibodu",
        "smtpSecure" => "tls",
        "port" => 587
      ],
			"jwt" => [
				"secret"=> "supersecretkeyyoushouldnotcommittogithub",
			],
			"general" => [
				"clientUrl" => "http://localhost:8080",
				"verifyEmailUrl" => "http://localhost:8080/verify-email/"
			],
			
		];
	});
};