<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class UserController extends BaseController 
{
  public function login(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $email = $data['email'];
    $password = $data['password'];
    $status = 200;
    $success = false;
    $rb = $this->findUserByEmail($email);

    if ($rb) {
      $hashPwd = $this->getPasswordById($rb->id);
      if (password_verify($password, $hashPwd)) {
        if ($rb->status === 'locked') {
          $type = "ACCOUNT_LOCKED";
          $resArr = [ "uid" => $rb->uid ];
          $logArr = $resArr;
        }
        elseif ($rb->status === 'disabled') {
          $type = "ACCOUNT_DISABLED";
          $resArr = [ "uid" => $rb->uid ];
          $logArr = $resArr;
        } 
        elseif ($rb->status === 'active') {
          $success = true;
          $type = "USER_AUTHENTICATED";
          $resArr = [ 
            "name" => $rb->first_name,
            "email" => $rb->email,
            "verified" => $rb->verified,
            "uid" => $rb->uid,
          ];
          $token = $this->getJwtToken($resArr);
          $resArr['token'] = $token;
          $logArr = [ "user_id" => $rb->uid ];
        } else {
          $type = "INVALID_LOGIN_STATUS";
          $resArr = [ "uid" => $rb->uid ];
          $logArr = $resArr;
        }
      } else {
        $type = "INVALID_LOGIN_PASSWORD";
        $resArr = [ "uid" => $rb->uid ];
        $logArr = [ "user_id" => $rb->uid ];
      }
    } else {
      $type = "INVALID_LOGIN_EMAIL";
      $resArr = [ "email" => $email ];
      $logArr = $resArr;
    }  

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }
  
  public function register(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $data['verified'] = 0;
    $data['status'] = 'active';
    $data['last_name'] = '';
    $status = 500;
    $success = false;

    // Validate data
    $vResult = $this->vRegister($data);

    // Register user
    if (empty($vResult)) {
      $rec = $this->addUser($data);     
      $id =  $rec['id'];
      $uid = $rec['uid'];

      // Add password
      $pwdHash = $this->pwdHash($data['password']);
      $this->addPwd($id, $pwdHash);
      
      // Variables to send email
      $token = $this->createUserToken($id, 'verify-email');
      $verifyUrl = $this->settings['general']['verifyEmailUrl'].$token;

      // Send verification email
      $mailArr = [ "email" => $data['email'], "first_name" => $data['first_name'], "verify_url" => $verifyUrl ];
      //$mail = $this->sendMail($mArr, 'verify-new-account');
      $mail = true;// if false we should change the status could not send email

      $success = true;
      $status = 201;
      $type = "USER_REGISTERED";
      $resArr = [ "name" => $data['first_name'], "uid" => $uid ];
      $logArr = [ "user_id" => $uid ];
    }

    else {
      $status = 200;
      $type = "USER_NOT_REGISTERED";
      $resArr = [ "name" => "VALIDATION_ERROR", "value" => $vResult ];
      $logArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function exist(Request $request, Response $response) {
    $status = 500;
    $success = false;
    $data = $request->getParsedBody();
    $rb = $this->findUserByEmail($data['email']);
    
    if (empty($rb)) {
      $success = true;
      $status = 201;
      $type = "EMAIL_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "EMAIL_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  public function user (Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $data = $this->getJwtTokenData($headers);
    $result = [ "success" => true, "status" => 200, "type" => 'token', "response" => $data ];
    return $this->respondWithData($response,$result);
  }

  public function logout (Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $data = $this->getJwtTokenData($headers);
    $result = [ "success" => true, "status" => 200, "type" => 'token', "response" => $data ];
    return $this->respondWithData($response,$result);
  }

  private function addUser($data) {
    $currentDate = date('Y-m-d H:i:s');
    $uid = $this->getUid();
    $rb = R::dispense('users');
    $rb->uid = $uid;
    $rb->first_name = $data['first_name'];
    $rb->last_name = $data['last_name'];   
    $rb->email = $data['email'];
    $rb->verified = $data['verified'];
    $rb->status = $data['status'];
    $rb->created_date = $currentDate;
    $rb->modified_date = $currentDate;
    $id = R::store($rb);
    return [ "id" => $id, "uid" => $uid ];
  }

  private function vRegister($data) {
    $param['first_name'] = [
      "value" => $data['first_name'],
      "type" => [ "isEmpty" => "First name is required"]
    ];
    // $param['last_name'] = [
    //   "value" => $data['last_name'],
    //   "type" => [ "isEmpty" => "Last name is required"]
    // ];
    $param['email'] = [
      "value" => $data['email'],
      "type" => [        
        "isEmpty" => "Email is required", 
        "isNotEmail" => "Invalid email format",
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
      "value" => [
        "match" => $data['password'],
        "value" => $data['confirm_password'],
      ],
      "type" => [ 
        "isNotMatch" => "Passwords do not match"
      ]
    ];
    
    return $this->validateData($param);
  }

  private function findUserByEmail($email) {
    $rb  = R::findOne( 'users', ' email = ? and status != ? ', [$email, 'delete']);
    if (empty($rb)) {
      return false;
    } else {
      return $rb;
    }
  }

  private function getPasswordById($id) {
    $rb  = R::findOne( 'passwords', ' user_id = ? and active = ? ', [$id, true]);
    if (empty($rb)) {
      return false;
    } else {
      return $rb->password;
    }
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

  private function addPwd($id, $password) {
    $this->diactivatePwd($id);
    $rb = R::dispense('passwords');
    $rb->user_id = $id;
    $rb->password = $password;
    $rb->active = true;
    $rb->create_date = date("Y-m-d H:i:s");
    R::store($rb);
  }

  private function diactivatePwd($id) {
    $rb = R::findOne( 'passwords', ' user_id = ? and active = ? ', [ $id, true ] );
    if (!empty($rb)) {
      $rb->active = false;
      R::store($rb);
    }    
  }
}