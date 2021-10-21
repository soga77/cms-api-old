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
use \RedBeanPHP\R;

abstract class BaseController
{
  protected $settings;

  protected $logger;

  protected $fileManager;


  public function __construct(ContainerInterface $container)
	{
    $this->settings = $container->get('settings');
    $this->fileManager = $this->settings['fileManager'];
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

  protected function respondWithString(Response $response, $payload, $status = 200)
  {
    $json = json_encode($payload, JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response
      ->withStatus($status);
  }

  protected function getUid() {
		return bin2hex(random_bytes(16));
	}

	protected function getToken() {
		return bin2hex(openssl_random_pseudo_bytes(16));
  }

  protected function getUserID($uid) {
    $result = false;
    $rb  = R::findOne( 'users', 'uid = ?', [$uid]);
    if (!empty($rb->id)) {
      $result = $rb->id; 
    }
		return $result;
  }

  protected function getModuleID($args) {
    $id = ModuleController::getModuleByUID($args);
    return $id;
  }

  protected function slugify($string) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    return $slug;
  }

  protected function getTemplateID($args) {
    $id = EmailTemplateController::getTemplateByUID($args);
    return $id;
  }

  protected function getRoleID($uid) {
    $arr = RoleController::getRoleByUID($uid);
    return $arr;
  }

  protected function getUserRoleNames($id) {
    $arr = RoleController::getUserRoleNamesByID($id);
    return $arr;
  }
  
  protected function getUserRole($id) {
    $arr = RoleController::getUserRoleUIDByID($id);
    return $arr;
  }
  

  protected function getRolePermissionID($uid) {
    $id = PermissionController::getRolePermissionByUID($uid);
    return $id;
  }  

  protected function getJwtToken($data) {
		$jwt = new JwtToken($this->settings['jwt']);
    return $jwt->getJwtToken($data);
  }

  protected function getJwtTokenData($headers) {
    $token = str_replace('Bearer ', '', $headers['Authorization'][0]);
    $jwt = new JwtToken($this->settings['jwt']);
    return $jwt->getJwtData($token);
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