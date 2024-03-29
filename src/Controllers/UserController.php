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
      //add user data
      $rec = $this->addUser($data);
      $id =  $rec['id'];
      $uid = $rec['uid'];
      // add user roles
      $rec['role_names'] = $this->addUserRole($id, $data['roles']);
      unset($rec['id']);
      // send notifications as required


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
    //$data['change_password'] = $data['change_password'] ? 1 : 0;
    //$data['email_verified'] = $data['email_verified'] ? 1 : 0;
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

  public function pwdChange(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokendata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $user = $this->getUser($data);
    $id = $user['id'];
    $name = $user['first_name'].' '.$user['last_name'];
    $status = 500;
    $success = false;  

    // Update record
    if ($id) {
           
      // Validate data
      $vResult = $this->vPwdChange($data);      

      if (empty($vResult)) {  
        $pwdHash = $this->pwdHash($data['password']);
        $this->addPwd($id, $pwdHash, $data['change_password']);

        $success = true;
        $status = 201;
        $type = "PASSWORD_UPDATED";
        $logArr = [ "owner" => $tokendata['uid'], "user_id" => $data['uid']];
        $resArr = ["uid" => $data['uid'], "name" => $name];
      } else {
        $status = 200;
        $type = "PASSWORD_VALIDATION_ERROR";
        $logArr = [ "owner" => $tokendata['uid'], "user_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "PASSWORD_NOT_UPDATED";
      $logArr = [ "owner" => $tokendata['uid'], "role_id" => $data['uid'] ];
      $resArr = [ "description" => "Invalid request recieved" ]; 
    }       
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function account(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokendata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $id = $this->getID($data); //get record to update
    $status = 500;
    $success = false;    
    
    // Update record
    if ($id) {
      $cArr = [];
      $rb = R::load('users', $id);
      $firstName = strtolower($rb->first_name);
      $lastName = strtolower($rb->last_name);
      $email = strtolower($rb->email);
      $status = strtolower($rb->status);
      $verified = $rb->email_verified;
      // $createDate = $rb->created_date;
      $oldRoles = $this->getUserRole($id);
      
      
      if (isset($data['first_name']) && strtolower($data['first_name']) !== $firstName) {
        $rb->first_name = $data['first_name'];
        array_push($cArr, 'first_name');
      }
      if (isset($data['last_name']) && strtolower($data['last_name']) !== $lastName) {
        $rb->last_name = $data['last_name'];
        array_push($cArr, 'last_name');
      }
      if (isset($data['email']) && strtolower($data['email']) !== $email) {
        $rb->email = $data['email'];
        array_push($cArr, 'email');
      }
      if (isset($data['email_verified']) && $data['email_verified'] !== $verified) {
        $rb->email_verified = $data['email_verified'];
        array_push($cArr, 'email_verified');
      }
      if (isset($data['status']) && strtolower($data['status']) !== $status) {
        $rb->status = $data['status'];
        array_push($cArr, 'status');
      }

      if (isset($data['roles']) && (!empty(array_diff($oldRoles, $data['roles'])) || !empty(array_diff($data['roles'], $oldRoles)))) {
        array_push($cArr, 'roles');
      }
      
      // Validate data
      $vResult = $this->vAccount($data, $cArr);      

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $userId = $this->getUserId($tokendata['uid']);
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          $rb->modified_by = $userId;
          R::store($rb);

          // edit user roles
          $newRoles = isset($data['roles']) ? $data['roles'] : [];
          
          $addRoles = array_diff($newRoles, $oldRoles);
         
          $delRoles = array_diff($oldRoles, $newRoles);
          if (!empty($delRoles)) {
            $this->deleteUserRole($id, $delRoles);
          }
          if (!empty($addRoles)) {
            $this->addUserRole($id, $addRoles);
          }

          $success = true;
          $status = 201;
          $type = "USER_ACCOUNT_UPDATED";
          $logArr = [ "owner" => $tokendata['uid'], "user_id" => $data['uid'], "changes" => implode(", ", $cArr)];
          $resArr = ["uid" => $data['uid'] ,"name" => $data['first_name'].' '.$data['last_name'], "email" => $data['email'], "status" => $data['status'], "email_verified" => $data['email_verified'], "role_names" => $this->getUserRoleNames($id), "modified_date" => $currentDate, "changes" => $cArr ];
        } else {
          $status = 200;
          $type = "USER_ACCOUNT_NO_CHANGES";
          $logArr = [ "owner" => $tokendata['uid'], "user_id" => $data['uid'] ];
          $resArr = [ "description" => "No changes made to user account" ]; 
        }
      } else {
        $status = 200;
        $type = "USER_ACCOUNT_VALIDATION_ERROR";
        $logArr = [ "owner" => $tokendata['uid'], "user_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "USER_ACCOUNT_NOT_UPDATED";
      $logArr = [ "owner" => $tokendata['uid'], "role_id" => $data['uid'] ];
      $resArr = [ "description" => "Invalid request recieved" ]; 
    }       
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function delete(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokendata = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $id = $this->getID($args);

    if ($id) {
      $rb = R::load('users', $id);
      $name = $rb->email;
      R::trash( $rb );

      $rb = R::find('passwords', 'user_id = ?', [$id]);
      R::trashAll( $rb );

      $rb = R::find('userrolemap', 'user_id = ?', [$id]);
      R::trashAll( $rb );

      $success = true;
      $status = 200;
      $type = "USER_DELETED";
      $logArr = [ "owner" => $tokendata['uid'],  "user_uid" => $args['uid'], "block_name" => $name ];
      $resArr = [ "name" => $name ];
    } else{
      $status = 200;
      $type = "USER_NOT_DELETED";
      $logArr = [ "owner" => $tokendata['uid'],  "user_uid" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
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
        "role_names" => $this->getUserRoleNames($record->id),
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

  public function item(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokendata = $this->getJwtTokenData($headers);

    $id = $this->getID($args);
    $status = 500;
    $success = false;
    
    if ($id) {
      $sql = "SELECT u.uid, u.first_name, u.last_name, u.email, u.status, u.email_verified, u.created_date, u.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM users u ";
      $sql .= "LEFT JOIN users u1 ON u.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON u.modified_by = u2.id ";
      $sql .= "WHERE u.id = :id";

      $rb = R::getAll($sql, [':id' => $id]); 

      // var_dump($sql);
      // die();
      // $rb[0]['email_verified'] = $rb[0]['email_verified'] === 1 ? true : false;
      $rb[0]['roles'] = $this->getUserRole($id);
      $rb[0]['role_names'] = $this->getUserRoleNames($id);

      $success = true;
      $status = 200;
      $type = "USER_FOUND";
      $logArr = [ "owner" => $tokendata['uid'],  "user_id" => $args['uid'] ];
      $resArr = $rb[0];
    } else{
      $status = 200;
      $type = "USER_NOT_FOUND";
      $logArr = [ "owner" => $tokendata['uid'],  "user_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
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

  private function getUser($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('users', 'uid = ?', [$args['uid']]);
      if (!empty($rb)) {
        $result = $rb; 
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

  private function deleteUserRole ($id, $arr) {
    foreach ($arr as $role_uid) {
      $role_id = $this->getRoleID($role_uid);      
      $rb = R::find('userrolemap', 'role_id = :role_id AND user_id = :user_id', [ ':role_id' => $role_id, ':user_id' => $id]);
      R::trashAll( $rb );
    }
  }

  private function diactivatePwd($id) {
    $rb = R::findOne( 'passwords', ' user_id = ? and active = ? ', [ $id, true ] );
    if (!empty($rb)) {
      $rb->active = false;
      R::store($rb);
    }    
  }

  

  private function  vPwdChange($data) {    
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

  private function  vUser($data, $cArr = NULL) {    
    // var_dump($data);
    // die();
    if (is_null($cArr) || isset($cArr['email'])) {
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

  private function  vAccount($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['email'])) {
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
    

    return $this->validateData($param);
  }
}