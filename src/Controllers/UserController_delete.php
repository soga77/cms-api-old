<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class UserController extends BaseController
{
  public function register(Request $request, Response $response) {    
    
    $data = $request->getParsedBody();

    // Validate data
    $vResult = $this->vRegister($data);

    // Register user
    if (empty($vResult)) {
      $uid = $this->getUid();
      $rb = R::dispense('users');
      $rb->uid = $uid;
      $rb->first_name = $data['first_name'];
      $rb->last_name = $data['last_name'];
      $rb->email = $data['email'];
      $rb->password = $this->pwdHash($data['password']);
      $rb->create_date = date("Y-m-d H:i:s");
      $id = R::store($rb);

      $token = $this->createUserToken($id, 'verify-email');
      $verifyURL = $this->settings['general']['clientBaseURL']."/ve/".$token;
      
      // Send verification email
      $mArr = [ "email" => $data['email'], "first_name" => $data['first_name'], "last_name" => $data['last_name'], "verify_url" => $verifyURL ];
      //$mail = $this->sendMail($mArr, 'verify-new-account');
      $mail = true;

      $result = [ "success" => true, "uid" => $uid, "mail" => $mail ];

    } 
    // Set validation error(s)
    else {
      $result = [ "success" => false, "validation" => $vResult ];
    }
       
    // Return response
    return $this->respondWithData($response,$result);
  }

  private function vRegister($data) {
    $param['first_name'] = [
      "value" => $data['first_name'],
      "type" => [ "isEmpty" => "First name is required"]
    ];
    $param['last_name'] = [
      "value" => $data['last_name'],
      "type" => [ "isEmpty" => "Last name is required"]
    ];
    $param['email'] = [
      "value" => $data['email'],
      "type" => [         
        "isNotEmail" => "Email address is required",
        "isNotEmailExist" => "Account with email address already exist"
      ]
    ];
    $param['password'] = [
      "value" => $data['password'],
      "type" => [ 
        "isEmpty" => "Password is required",
        "isNotMediumPwd" => "Password does not meet minimum requirements"
      ]
    ];
    $param['confirm_password'] = [
      "value" => $data['confirm_password'],
      "match" => $data['password'],
      "type" => [ 
        "isNotMatch" => "Passwords do not match"
      ]
    ];
    
    return $this->validateData($param);
  }

  private function pwdHash($password){
    return password_hash($password, PASSWORD_DEFAULT);
  }

  private function createUserToken($id, $type) {
    $token = $this->getToken();
    $rb = R::dispense('usertokens');
    $rb->user_id = $id;
    $rb->type = $type;
    $rb->token = $token;
    $rb->create_date = date("Y-m-d H:i:s");
    R::store($rb);
    return $token;
  }
}