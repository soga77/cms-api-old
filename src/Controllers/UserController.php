<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class UserController extends BaseController 
{
  public function add(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $status = 500;
    $success = false;
        
    // Validate data
    // var_dump($data);
    // die();
    $vResult = $this->vUser($data);

    // Add Block
    if (empty($vResult)) {      
      $data['created_by'] = $this->getUserId($tokenData['uid']);     
      $rec = $this->addUser($data);
      $id =  $rec['id'];
      $uid = $rec['uid'];
      $rec['roles'] = $this->addUserRole($id, $data['roles']);
      unset($rec['id']);

      $success = true;
      $status = 201;
      $type = "USER_ADDED";
      $logArr = [ "owner" => $tokenData['uid'], "user_uid" => $uid ];
      $resArr = $rec;
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "USER_VALIDATION_ERROR";
      $logArr = [ "owner" => $tokenData['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function addUser($data) {
    $data['change_password'] = $data['change_password'] ? 1 : 0;
    $data['email_verified'] = $data['email_verified'] ? 1 : 0;
    $currentDate = date('Y-m-d H:i:s');
    $uid = $this->getUid();
    $rb = R::dispense('users');
    $rb->uid = $uid;
    $rb->first_name = $data['first_name'];
    $rb->last_name = $data['last_name'];
    $rb->email = $data['email'];
    $rb->email_verified = $data['email_verified'];
    $rb->status = $data['status'];
    $rb->last_login = null;
    $rb->created_date = $currentDate;
    $rb->modified_date = null;
    $rb->created_by = $data['created_by'];
    $rb->modified_by = null;
    $id = R::store($rb);

    $rec = ["id" => $id, "uid" => $uid, "name" => $data['first_name'].' '.$data['last_name'], "email" => $data['email'], "status" => $data['status'], "email_verified" => $data['email_verified'], "created_date" => $currentDate];

    $pwdHash = $this->pwdHash($data['password']);
    $this->addPwd($id, $pwdHash, $data['change_password']);

    return $rec;
  }
  
  public function items(Request $request, Response $response) {
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $status = 500;
    $success = false;
    $records = R::findAll('users', ' order by first_name asc ');
    $row = [];
    foreach ($records as $record) {
      $row[] = [
        "uid" => $record->uid,
        "name" => $record->first_name . ' ' . $record->last_name,
        // "first_name" => $record->first_name,
        // "last_name" => $record->last_name,
        "email" => $record->email,
        "email_verified" => $record->email_verified,
        "roles" => $this->getUserRoleNames($record->id),
        // "avatar" => "man.png",
        "status" => $record->status,
        "last_login_date" => $record->last_login_date,
        "modified_date" => $record->modified_date,
        "created_date" => $record->created_date
      ];
    }
    if (empty($row)) {
      $status = 200;
      $type = "USERS_NOT_RETRIVED";
      $resArr = []; 
      $logArr = [ "owner" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "USERS_RETRIVED";
      $resArr = $row; 
      $logArr = [ "owner" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function emailExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $email = $data['email'];

    $status = 500;
    $success = false;

    if ($id) {
      $rb  = R::findOne( 'users', 'email LIKE ? AND id != ?', [$email, $id]); 
    } else {
      $rb  = R::findOne( 'users', 'email LIKE ?', [$email]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "USER_EMAIL_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "USER_EMAIL_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('users', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function pwdHash($password){
    return password_hash($password, PASSWORD_DEFAULT);
  }

  private function addPwd($id, $password, $cPassword = 0) {
    $this->diactivatePwd($id);
    $rb = R::dispense('passwords');
    $rb->user_id = $id;
    $rb->password = $password;
    $rb->active = true;
    $rb->change_password = $cPassword;
    $rb->created_date = date("Y-m-d H:i:s");
    R::store($rb);
  }

  private function addUserRole ($id, $arr) {
    foreach ($arr as $role_uid) {
      $rb = R::dispense('userrolemap');
      $rb->user_id = $id;
      $rb->role_id = $this->getRoleID($role_uid);
      R::store($rb);
    }
    return $this->getUserRoleNames($id);
  }

  private function diactivatePwd($id) {
    $rb = R::findOne( 'passwords', ' user_id = ? and active = ? ', [ $id, true ] );
    if (!empty($rb)) {
      $rb->active = false;
      R::store($rb);
    }    
  }

  private function  vUser($data, $cArr = NULL) {    
    // var_dump($data);
    // die();
    if (is_null($cArr) || isset($cArr['name'])) {
      $param['email'] = [
        "value" => [
          "value" => isset($data['email'])? $data['email'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Email is required",
          "isNotEmail" => "Invalid email format",
          "isEmailExist" => "Email address already exist"
        ]
      ];
    } else {
      $param['email'] = [
        "value" => [
          "value" => isset($data['email'])? $data['email'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Email is required"
        ]
      ];
    } 
    
    $param['first_name'] = [
      "value" => [
        "value" => isset($data['first_name'])? $data['first_name'] : '',
      ],
      "type" => [ "isEmpty" => "First name is required"]
    ];

    $param['last_name'] = [
      "value" => [
        "value" => isset($data['last_name'])? $data['last_name'] : '',
      ],
      "type" => [ "isEmpty" => "Last name is required"]
    ];

    $param['status'] = [
      "value" => [
        "value" => isset($data['status'])? $data['status'] : '',
      ],
      "type" => [ "isEmpty" => "Status is required"]
    ];  

    $param['roles'] = [
      "value" => [
        "value" => isset($data['roles'])? $data['roles'] : '',
      ],
      "type" => [ "isEmpty" => "Role selections is required"]
    ];  
    
    $param['password'] = [
      "value" => [
        "value" => isset($data['password'])? $data['password'] : '',
      ],
      "type" => [ 
        "isEmpty" => "Password is required",
        "isNotMediumPwd" => "Password does not meet minimum requirements"
      ]
    ];  

    $param['confirm_password'] = [
      "value" => [
        "match" => isset($data['password'])? $data['password'] : '',
        "value" => isset($data['confirm_password'])? $data['confirm_password'] : '',
      ],
      "type" => [ 
        "isNotMatch" => "Passwords do not match"
      ]
    ];  
    

    return $this->validateData($param);
  }
}