<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class RoleController extends BaseController 
{
  public function add(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $status = 500;
    $success = false;
    
    // Validate data
    $vResult = $this->vRoles($data);

    // Add Block
    if (empty($vResult)) {
      $userId = $this->getUserId($auth['uid']);
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('roles');
      $rb->uid = $uid;
      $rb->name = $data['name'];
      $rb->alias = $data['alias'];
      $rb->description = $data['description'];
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      if (!empty($data['permissions'])) {
        $this->addRolePermissions($id, $data['permissions']);
      }

      $success = true;
      $status = 201;
      $type = "ROLE_ADDED";
      $logArr = [ "user_id" => $auth['uid'] ];
      $resArr = ["uid" => $uid, "name" => $data['name'], "alias" => $data['alias'], "modified_date" => $currentDate];     
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "ROLE_VALIDATION_ERROR";
      $logArr = [ "user_id" => $auth['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function addRolePermissions ($id, $arr) {
    foreach ($arr as $perm_uid) {
      $perm_id = $this->getRolePermissionID($perm_uid);
      $rb = R::dispense('rolepermissionsmap');
      $rb->role_id = $id;
      $rb->permission_id = $perm_id;
      R::store($rb);
    }    
  }

  private function deleteRolePermissions ($id, $arr) {
    foreach ($arr as $perm_uid) {
      $perm_id = $this->getRolePermissionID($perm_uid);
      $rb = R::find('rolepermissionsmap', 'role_id = :role_id AND permission_id = :perm_id', [ ':role_id' => $id, ':perm_id' => $perm_id]);
      R::trashAll( $rb );
    }    
  }

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
      $rb = R::load('roles', $id);
      $name = strtolower($rb->name);
      $alias = strtolower($rb->alias);
      $description = strtolower($rb->description);
      $createDate = $rb->created_date;
      $oldPerm = $this->getRolePermissionsUid($id);

      if (isset($data['name']) && strtolower($data['name']) !== $name) {
        $rb->name = $data['name'];
        array_push($cArr, 'name');
      }
      if (isset($data['alias']) && strtolower($data['alias']) !== $alias) {
        $rb->alias = $data['alias'];
        array_push($cArr, 'alias');
      }
      if (isset($data['description']) && strtolower($data['description']) !== $description) {
        $rb->description = $data['description'];
        array_push($cArr, 'description');
      }

      if (isset($data['permissions']) && (!empty(array_diff($oldPerm, $data['permissions'])) || !empty(array_diff($data['permissions'], $oldPerm)))) {
        array_push($cArr, 'permissions');
      }
      
      // Validate data
      $vResult = $this->vRoles($data, $cArr);      

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $userId = $this->getUserId($auth['uid']);
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          $rb->modified_by = $userId;
          R::store($rb);

          // edit role permissions
          $newPerm = isset($data['permissions']) ? $data['permissions'] : [];
          $addPerm = array_diff($newPerm, $oldPerm);
          $delPerm = array_diff($oldPerm, $newPerm);
          if (!empty($delPerm)) {
            $this->deleteRolePermissions($id, $delPerm);
          }
          if (!empty($addPerm)) {
            $this->addRolePermissions($id, $addPerm);
          }

          $success = true;
          $status = 201;
          $type = "ROLE_UPDATED";
          $logArr = [ "user_id" => $auth['uid'], "role_id" => $data['uid'], "changes" => implode(", ", $cArr)];
          $resArr = ["uid" => $data['uid'] ,"name" => $data['name'], "alias" => $data['alias'], "created_date" => $createDate, "modified_date" => $currentDate, "changes" => $cArr ]; 
        } else {
          $status = 200;
          $type = "ROLE_NO_CHANGES";
          $logArr = [ "user_id" => $auth['uid'], "role_id" => $data['uid'] ];
          $resArr = [ "description" => "No changes made to user role" ]; 
        }
      } else {
        $status = 200;
        $type = "ROLE_VALIDATION_ERROR";
        $logArr = [ "user_id" => $auth['uid'], "role_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "ROLE_NOT_UPDATED";
      $logArr = [ "user_id" => $auth['uid'], "role_id" => $data['uid'] ];
      $resArr = [ "description" => "Invalid request recieved" ]; 
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
    $records = R::findAll('roles', ' order by name asc ');
    $row = [];
    foreach ($records as $record) {
      $row[] = [
        "uid" => $record->uid,
        "name" => $record->name,
        "alias" => $record->alias,
        "modified_date" => $record->modified_date
      ];
    }
    if (empty($row)) {
      $status = 200;
      $type = "ROLES_NOT_RETRIVED";
      $resArr = []; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "ROLES_RETRIVED";
      $resArr = $row; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }
  
  public function delete(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $id = $this->getID($args);

    if ($id) {
      $rb = R::load('roles', $id);
      $name = $rb->name;
      R::trash( $rb );

      $rb = R::find('rolepermissionsmap', 'role_id = ?', [$id]);
      R::trashAll( $rb );

      $success = true;
      $status = 200;
      $type = "ROLE_DELETED";
      $logArr = [ "user_id" => $auth['uid'],  "role_id" => $args['uid'], "block_name" => $name ];
      $resArr = [ "name" => $name ];
    } else{
      $status = 200;
      $type = "ROLE_NOT_DELETED";
      $logArr = [ "user_id" => $auth['uid'],  "role_id" => $args['uid'], ];
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
      $sql = "SELECT r.uid, r.name, r.alias, r.description, r.created_date, r.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM roles r ";
      $sql .= "LEFT JOIN users u1 ON r.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON r.modified_by = u2.id ";
      $sql .= "WHERE r.id = :id";

      $rb = R::getAll($sql, [':id' => $id]); 

      $rp = $this->getRolePermissions($id);
      if (!empty($rp)) {
        $rb[0]['role_permissions'] = $rp;
      }

      $srp = $this->getRolePermissionsUid($id);
      if (!empty($rp)) {
        $rb[0]['permissions'] = $srp;
      }

      $success = true;
      $status = 200;
      $type = "ROLE_FOUND";
      $logArr = [ "user_id" => $auth['uid'],  "role_id" => $args['uid'], "block_name" => $rb[0]['name'] ];
      $resArr = $rb[0];
    } else{
      $status = 200;
      $type = "ROLE_NOT_FOUND";
      $logArr = [ "user_id" => $auth['uid'],  "role_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getRolePermissionsUid($id) {
    $row = [];
    $sql = "SELECT uid FROM permissions ";
    $sql .= "WHERE id IN (SELECT permission_id FROM rolepermissionsmap WHERE role_id = :id)";
    $records = R::getAll($sql, [':id' => $id]);

    foreach ($records as $key => $record) {
      array_push($row, $record['uid']);
    }
    return $row;
  }

  private function getRolePermissions($id){
    $row = [];
    $sql = "SELECT DISTINCT id, uid, name  FROM modules ";
    $sql .= "WHERE id IN (SELECT module_id FROM permissions WHERE id IN (SELECT permission_id FROM rolepermissionsmap WHERE role_id = :id)) ";
    $sql .= "order by name asc";

    $records = R::getAll($sql, [':id' => $id]);

    foreach ($records as $key => $record) {

      $sql = "SELECT uid, alias FROM permissions WHERE module_id = :id ";
      $sql .= "AND id IN (SELECT permission_id FROM rolepermissionsmap WHERE role_id = :role_id) ";
      $sql .= "order by alias asc";

      $subRecords = R::getAll($sql, [':id' => $record['id'], ':role_id' => $id]);
      $sr = [];
      $ch = [];      

      if (!empty($subRecords)) {
        $sr = [
          "id" => $record['uid'],
          "name" => $record['name'],
        ];

        foreach ($subRecords as $subRecord) {
          $ch[] = [
            "id" => $subRecord['uid'],
            "name" => $subRecord['alias'],
          ]; 
        }
        $sr['children'] = $ch;

        array_push($row, $sr);
      }      
    };    

    return $row;
  }

  public function permissions(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $status = 500;
    $success = false;

    $records = R::findAll('modules', ' order by name asc ');
    $row = [];
    

    foreach ($records as $key => $record) {
      $subRecords = R::findAll('permissions', ' where module_id = ? order by alias asc ', [$record->id]); 
      $sr = [];
      $ch = [];      

      if (!empty($subRecords)) {
        $sr = [
          "id" => $record->uid,
          "name" => $record->name,
        ];

        foreach ($subRecords as $subRecord) {
          $ch[] = [
            "id" => $subRecord->uid,
            "name" => $subRecord->alias,
          ]; 
        }
        $sr['children'] = $ch;

        array_push($row, $sr);
      }      
    };    

    if (!empty($row)) {
      $success = true;
      $status = 200;
      $type = "ROLE_PERMISSIONS_FOUND";
      $logArr = [ "user_id" => $tokenData['uid'] ];
      $resArr = $row;
    } else{
      $status = 200;
      $type = "ROLE_PERMISSIONS_NOT_FOUND";
      $logArr = [ "user_id" => $tokenData['uid']];
      $resArr = [];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function duplicate(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $org_id = $this->getID($args);
    $org_perms = R::findAll('rolepermissionsmap', ' where role_id = ? ', [$org_id]);
    $status = 500;
    $success = false;
    
    if ($org_id) {
      $userId = $this->getUserId($auth['uid']);
      $uid = $this->getUid();
      $org = R::load('roles', $org_id);
      $newName = $this->duplicateBlockName($org->name);
      $newAlias = $this->duplicateBlockAlias($org->alias);
      $currentDate = date('Y-m-d H:i:s');
      $rb = R::dispense('roles');
      $rb->uid = $uid;
      $rb->name = $newName;
      $rb->alias = $newAlias;
      $rb->description = $org->description;
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      if (!empty($org_perms)) {
        foreach ($org_perms as $perm) {
          $rb = R::dispense('rolepermissionsmap');
          $rb->role_id = $id;
          $rb->permission_id = $perm->permission_id;
          R::store($rb);
        }
      }

      $success = true;
      $status = 201;
      $type = "ROLE_DUPLICATED";
      $logArr = [ "user_id" => $auth['uid'], "new_name" => $newName, "new_alias" => $newAlias ];
      $resArr = ["created_date" => $currentDate, "modified_date" => $currentDate, "uid" => $uid, "name" => $newName, "alias" => $newAlias ]; 

    } else{
      $status = 200;
      $type = "ROLE_NOT_DUPLICATED";
      $logArr = [ "user_id" => $auth['uid'],  "duplicated_from_id" => $args['uid'] ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function duplicateBlockName($name, $count = 0){
    $newName = $name.' Copy'.(($count == 0) ? '' : ' '.$count);
    $rb  = R::findOne( 'roles', ' name LIKE ? ', [$newName]); 
    if (empty($rb->id)) {
      return $newName;
    } else {
      $count = $count + 1;
      return $this->duplicateBlockName($name, $count);
    }
  }

  private function duplicateBlockAlias($alias, $count = 0){
    $newAlias = $alias.'-copy'.(($count == 0) ? '' : '-'.$count);
    $rb  = R::findOne( 'roles', ' alias LIKE ? ', [$newAlias]); 
    if (empty($rb->id)) {
      return $newAlias;
    } else {
      $count = $count + 1;
      return $this->duplicateBlockAlias($alias, $count);
    }
  }

  public function nameExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $name = $data['name'];

    $status = 500;
    $success = false;

    if ($id) {
      $rb  = R::findOne( 'roles', 'name LIKE ? AND id != ?', [$name, $id]); 
    } else {
      $rb  = R::findOne( 'roles', 'name LIKE ?', [$name]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "ROLE_NAME_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "ROLE_NAME_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  public function aliasExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $alias = $data['alias'];

    $status = 500;
    $success = false;

    if ($id) {
      $rb  = R::findOne( 'roles', 'alias LIKE ? AND id != ?', [$alias, $id]); 
    } else {
      $rb  = R::findOne( 'roles', 'alias LIKE ?', [$alias]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "ROLE_ALIAS_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "ROLE_ALIAS_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('roles', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function  vRoles($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['name'])) {
      $param['name'] = [
        "value" => [
          "value" => isset($data['name'])? $data['name'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Name is required",
          "isRoleNameExist" => "Name already exist"
        ]
      ];
    } else {
      $param['name'] = [
        "value" => [
          "value" => isset($data['name'])? $data['name'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Name is required"
        ]
      ];
    }    
    if (is_null($cArr) || isset($cArr['alias'])) {
      $param['alias'] = [
        "value" => [
          "value" => isset($data['alias'])? $data['alias'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Alias is required",
          "isNotAlias" => "Invalid alias format",
          "isRoleAliasExist" => "Alias already exist"
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
    $param['description'] = [
      "value" => [
        "value" => isset($data['description'])? $data['description'] : '',
      ],
      "type" => [ "isEmpty" => "Description is required"]
    ];

    return $this->validateData($param);
  }
}