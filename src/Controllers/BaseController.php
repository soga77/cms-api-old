<?php

namespace App\Controllers;

use App\Components\JwtToken;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpBadRequestException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Components\Validation;
use App\Components\Mailer;
use Psr\Log\LoggerInterface;

abstract class BaseController
{
  protected $settings;

  protected $logger;

  public function __construct(ContainerInterface $container)
	{
    $this->settings = $container->get('settings');
    $this->logger = $container->get(LoggerInterface::class);
  }

  protected function resolveArg(Request $request, string $name, array $args)
  {
    if (!isset($args[$name])) {
      throw new HttpBadRequestException($request, "Could not resolve argument `{$name}`.");
    }
    return $args[$name];
  }

  protected function respondWithData(Response $response, $payload, $status = 200)
  {
    $json = json_encode($payload, JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($status);
  }

  protected function getUid() {
		return bin2hex(random_bytes(16));
	}

	protected function getToken() {
		return bin2hex(openssl_random_pseudo_bytes(16));
  }

  protected function getJwtToken($data) {
		$jwt = new JwtToken($this->settings['jwt']);
    return $jwt->getJwtToken($data);
  }
  
  protected function validateData($data) {
		$validate = new Validation();
		return $validate->validateData($data);
	}
	
	protected function sendMail($param, $template) {
		$mailer = new Mailer($this->settings['mailer']);
		return $mailer->sendMail($param, $template);
	}

}