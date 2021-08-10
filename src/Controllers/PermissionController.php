<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class PermissionController extends BaseController 
{
  public function add(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $status = 500;
    $success = false;
    
    // Validate data
    $vResult = $this->vPermission($data);

    // Add template
    if (empty($vResult)) {
      $userId = $this->getUserId($auth['uid']);
      $moduleID = $this->getModuleID($data);
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('permissions');
      $rb->uid = $uid;
      $rb->module_id = $moduleID;
      $rb->alias = $data['alias'];
      $rb->description = $data['description'];
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      $success = true;
      $status = 201;
      $type = "PERMISSION_ADDED";
      $logArr = [ "user_id" => $auth['uid'] ];
      $resArr = ["uid" => $uid, "alias" => $data['alias'], "modified_date" => $currentDate];     
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "PERMISSION_VALIDATION_ERROR";
      $logArr = [ "user_id" => $auth['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function getPermissions($id) {
    $rb  = R::find( 'permissions', ' module_id = ? order by alias asc', [$id]); 
    foreach ($rb as $key => $value) {
      unset($rb[$key]['id']);
      unset($rb[$key]['module_id']);
    }
    if (empty($rb)) {
      $result = [];
    } else {
      $result = $rb;
    }
    return $result;
  }

  // private function permissions($id, $permissions) {
  //   foreach ($permissions as $permission) {
  //     if ($permission['action'] === 'add') {
  //       $this->permissionAdd($id, $permission);
  //     } elseif ($permission['action'] === 'edit') {
  //       $this->permissionEdit($id, $permission);
  //     } elseif ($permission['action'] === 'delete') {
  //       $this->permissionDelete($id, $permission);
  //     }
  //   }
  // }

  // private function notifications($id, $permissions) {
  //   $result = false;    
  // }



  public function edit(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $id = $this->getID($data); //get record to update
    $status = 500;
    $success = false;    
    
    // Update record
    if ($id) {
      $cArr = [];
      $rb = R::load('permissions', $id);
      $alias = strtolower($rb->alias);
      $description = strtolower($rb->description);
      $createDate = $rb->created_date;
      $moduleID = $this->getModuleID($data);

      if ($moduleID !== $rb->module_id) {
        $rb->module_id = $moduleID;
        array_push($cArr, 'module_id');
      }
      if (isset($data['alias']) && strtolower($data['alias']) !== $alias) {
        $rb->alias = $data['alias'];
        array_push($cArr, 'alias');
      }
      if (isset($data['description']) && strtolower($data['description']) !== $description) {
        $rb->description = $data['description'];
        array_push($cArr, 'description');
      }
      // Validate data
      $vResult = $this->vPermission($data, $cArr);

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $userId = $this->getUserId($auth['uid']);
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          $rb->modified_by = $userId;
          R::store($rb);

          $success = true;
          $status = 201;
          $type = "PERMISSION_UPDATED";
          $logArr = [ "user_id" => $auth['uid'], "permission_id" => $data['uid'], "changes" => implode(", ", $cArr)];
          $resArr = ["uid" => $data['uid'] , "alias" => $data['alias'], "modified_date" => $currentDate, "changes" => $cArr ]; 
        } else {
          $status = 200;
          $type = "PERMISSION_NO_CHANGES";
          $logArr = [ "user_id" => $auth['uid'], "permission_id" => $data['uid'] ];
          $resArr = [ "description" => "No changes made to permission" ]; 
        }
      } else {
        $status = 200;
        $type = "PERMISSION_VALIDATION_ERROR";
        $logArr = [ "user_id" => $auth['uid'], "permission_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "PERMISSION_NOT_UPDATED";
      $logArr = [ "user_id" => $auth['uid'], "permission_id" => $data['uid'] ];
      $resArr = [ "description" => "Invalid request recieved" ]; 
    }       
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function items(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);

    $id = $this->getModuleID($args);
    $status = 500;
    $success = false;

    $records = R::findAll('permissions', 'module_id = ? order by alias asc ', [$id]);
    $row = [];
    foreach ($records as $record) {
      $row[] = [
        "uid" => $record->uid,
        // "module_id" => $record->module_id,
        "alias" => $record->alias,
        // "description" => $record->description,
        "modified_date" => $record->modified_date
      ];
    }
    if (empty($row)) {
      $status = 200;
      $type = "PERMISSIONS_NOT_RETRIVED";
      $resArr = [];
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "PERMISSIONS_RETRIVED";
      $resArr = $row; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  static function getRolePermissionByUID($uid) {
    $result = false;

    $rb = R::findOne('permissions', 'uid = ?', [$uid]);

    if (!empty($rb->id)) {
      $result = $rb->id; 
    }
    
    return $result;
  }

  public function delete(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $id = $this->getID($args);

    if ($id) {
      $rb = R::load('permissions', $id);
      $alias = $rb->alias;
      R::trash( $rb );

      $success = true;
      $status = 200;
      $type = "PERMISSION_DELETED";
      $logArr = [ "user_id" => $auth['uid'],  "permission_id" => $args['uid'], "permission_alias" => $alias ];
      $resArr = [ "alias" => $alias ];
    } else{
      $status = 200;
      $type = "PERMISSION_NOT_DELETED";
      $logArr = [ "user_id" => $auth['uid'],  "permission_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function item(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $id = $this->getID($args);
    $status = 500;
    $success = false;
    
    if ($id) {
      $sql = "SELECT p.uid, p.alias, p.description, p.created_date, p.modified_date, m.name as module_name, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM permissions p ";
      $sql .= "LEFT JOIN modules m ON p.module_id = m.id ";
      $sql .= "LEFT JOIN users u1 ON p.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON p.modified_by = u2.id ";
      $sql .= "WHERE p.id = :id";

      $rb = R::getAll($sql, [':id' => $id]); 
      $success = true;
      $status = 200;
      $type = "PERMISSION_FOUND";
      $logArr = [ "user_id" => $auth['uid'],  "permission_id" => $args['uid'], "permission_alias" => $rb[0]['alias'] ];
      $resArr = $rb[0];
    } else{
      $status = 200;
      $type = "PERMISSION_NOT_FOUND";
      $logArr = [ "user_id" => $auth['uid'],  "permission_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }  

  public function aliasExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $alias = $data['alias'];
    $moduleID = $this->getModuleID($data);

    $status = 500;
    $success = false;

    // $rb  = R::findOne( 'permissions', ' module_id = ? AND alias LIKE ? ', [$value['value']['id']

    if ($id) {
      $rb  = R::findOne( 'permissions', 'module_id = ? AND alias LIKE ? AND id != ?', [$moduleID, $alias, $id]); 
    } else {
      $rb  = R::findOne( 'permissions', 'module_id = ? AND alias LIKE ?', [$moduleID, $alias]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "PERMISSION_ALIAS_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "PERMISSION_ALIAS_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('permissions', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function  vPermission($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['alias'])) {
      $moduleID = $this->getModuleID($data);
      $param['alias'] = [
        "value" => [
          "id" => $moduleID,
          "value" => isset($data['alias'])? $data['alias'] : '',
        ],
        "type" => [ 
          "isEmpty" => "Alias is required",
          "isNotAlias" => "Invalid alias format",
          "isPermissionAliasExist" => "Alias already exist"
        ]
      ];
    } else {
      $param['alias'] = [
        "value" => [
          "value" => isset($data['alias'])? $data['alias'] : '',
        ],
        "type" => [ 
          "isEmpty" => "Alias is required",
          "isNotAlias" => "Invalid alias format",
        ]
      ];
    }    
    $param['module_uid'] = [
      "value" => [
        "value" => isset($data['module_uid'])? $data['module_uid'] : '',
      ],
      "type" => [ "isEmpty" => "Module selection is required" ]
    ];
    $param['description'] = [
      "value" => [
        "value" => isset($data['description'])? $data['description'] : '',
      ],
      "type" => [ "isEmpty" => "Description is required"]
    ];

    return $this->validateData($param);
  }
}