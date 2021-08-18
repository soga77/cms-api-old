<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class BlockController extends BaseController 
{
  public function add(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokedata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $status = 500;
    $success = false;
    
    // Validate data
    $vResult = $this->vBlock($data);

    // Add Block
    if (empty($vResult)) {
      $userId = $this->getUserId($tokedata['uid']);
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('blocks');
      $rb->uid = $uid;
      $rb->name = $data['name'];
      $rb->alias = $data['alias'];
      $rb->content = $data['content'];
      $rb->parent_id = 0;
      $rb->version_no = 0;
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      $success = true;
      $status = 201;
      $type = "BLOCK_ADDED";
      $logArr = [ "user_id" => $tokedata['uid'] ];
      $resArr = ["uid" => $uid, "name" => $data['name'], "alias" => $data['alias'], "modified_date" => $currentDate];     
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "BLOCK_VALIDATION_ERROR";
      $logArr = [ "user_id" => $tokedata['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function createVersion($id) {
    $ver = R::load('blocks', $id);
    $uid = $this->getUid();

    $vb = R::findOne('blocks', 'where parent_id = ? order by version_no desc', [$id]);    
    $verNo = empty($vb) ? 1 : $vb->version_no + 1;

    $rb = R::dispense('blocks');
    $rb->uid = $uid;
    $rb->name = $ver->name;
    $rb->alias = $ver->alias;
    $rb->content = $ver->content;
    $rb->parent_id = $id;
    $rb->version_no = $verNo;
    $rb->created_date = $ver->created_date;
    $rb->modified_date = $ver->modified_date;
    $rb->created_by = $ver->created_by;
    $rb->modified_by = $ver->modified_by;
    R::store($rb);
  }

  public function edit(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokedata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $id = $this->getID($data); //get record to update
    $status = 500;
    $success = false;    
    
    // Update record
    if ($id) {
      $cArr = [];
      $rb = R::load('blocks', $id);
      $name = strtolower($rb->name);
      $alias = strtolower($rb->alias);
      $content = strtolower($rb->content);
      $createDate = $rb->created_date;

      if (isset($data['name']) && strtolower($data['name']) !== $name) {
        $rb->name = $data['name'];
        array_push($cArr, 'name');
      }
      if (isset($data['alias']) && strtolower($data['alias']) !== $alias) {
        $rb->alias = $data['alias'];
        array_push($cArr, 'alias');
      }
      if (isset($data['content']) && strtolower($data['content']) !== $content) {
        $rb->content = $data['content'];
        array_push($cArr, 'content');
      }
      
      // Validate data
      $vResult = $this->vBlock($data, $cArr);      

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $this->createVersion($id);
          $userId = $this->getUserId($tokedata['uid']);
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          $rb->modified_by = $userId;
          R::store($rb);

          $success = true;
          $status = 201;
          $type = "BLOCK_UPDATED";
          $logArr = [ "user_id" => $tokedata['uid'], "block_id" => $data['uid'], "changes" => implode(", ", $cArr)];
          $resArr = ["uid" => $data['uid'] ,"name" => $data['name'], "alias" => $data['alias'], "created_date" => $createDate, "modified_date" => $currentDate, "changes" => $cArr ]; 
        } else {
          $status = 200;
          $type = "BLOCK_NO_CHANGES";
          $logArr = [ "user_id" => $tokedata['uid'], "block_id" => $data['uid'] ];
          $resArr = [ "description" => "No changes made to block" ]; 
        }
      } else {
        $status = 200;
        $type = "BLOCK_VALIDATION_ERROR";
        $logArr = [ "user_id" => $tokedata['uid'], "block_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "BLOCK_NOT_UPDATED";
      $logArr = [ "user_id" => $tokedata['uid'], "block_id" => $data['uid'] ];
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
    $records = R::findAll('blocks', ' where parent_id = 0 order by name asc ');
    $row = [];
    foreach ($records as $record) {
      $row[] = [
        "uid" => $record->uid,
        "name" => $record->name,
        "alias" => $record->alias,
        "content" => $record->content,
        "modified_date" => $record->modified_date
      ];
    }
    if (empty($row)) {
      $status = 200;
      $type = "BLOCKS_NOT_RETRIVED";
      $resArr = []; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "BLOCKS_RETRIVED";
      $resArr = $row; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function hasVersions($id) {
    $result = false;
    $rb = R::findAll('blocks', 'where parent_id = ?', [$id]);
    if (!empty($rb)) {
      $result = count($rb);
    }
    return $result;
  }

  public function versions(Request $request, Response $response, $args) {
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $id = $this->getID($args);
    $status = 500;
    $success = false;

    if ($id) {
      $sql = "SELECT b.uid, b.name, b.alias, b.parent_id, b.version_no, b.content, b.created_date, b.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM blocks b ";
      $sql .= "LEFT JOIN users u1 ON b.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON b.modified_by = u2.id ";
      $sql .= "WHERE b.parent_id = :id " ;
      $sql .= "ORDER by b.version_no DESC";
      $rb = R::getAll($sql, [':id' => $id]);
    }
    
    if (empty($rb)) {
      $status = 200;
      $type = "BLOCK_VERSIONS_NOT_RETRIVED";
      $resArr = []; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "BLOCK_VERSIONS_RETRIVED";
      $resArr = $rb; 
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
    $tokedata = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $id = $this->getID($args);

    if ($id) {
      $rb = R::load('blocks', $id);
      $name = $rb->name;
      R::trash( $rb );

      $rb = R::find('blocks', 'parent_id = ?', [$id]);
      R::trashAll( $rb );

      $success = true;
      $status = 200;
      $type = "BLOCK_DELETED";
      $logArr = [ "user_id" => $tokedata['uid'],  "block_id" => $args['uid'], "block_name" => $name ];
      $resArr = [ "name" => $name ];
    } else{
      $status = 200;
      $type = "BLOCK_NOT_DELETED";
      $logArr = [ "user_id" => $tokedata['uid'],  "block_id" => $args['uid'], ];
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
    $tokedata = $this->getJwtTokenData($headers);

    $id = $this->getID($args);
    $status = 500;
    $success = false;
    
    if ($id) {
      $sql = "SELECT b.uid, b.name, b.alias, b.content, b.created_date, b.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM blocks b ";
      $sql .= "LEFT JOIN users u1 ON b.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON b.modified_by = u2.id ";
      $sql .= "WHERE b.id = :id";

      $rb = R::getAll($sql, [':id' => $id]); 
      $rb[0]['has_versions'] = $this->hasVersions($id);

      $success = true;
      $status = 200;
      $type = "BLOCK_FOUND";
      $logArr = [ "user_id" => $tokedata['uid'],  "block_id" => $args['uid'], "block_name" => $rb[0]['name'] ];
      $resArr = $rb[0];
    } else{
      $status = 200;
      $type = "BLOCK_NOT_FOUND";
      $logArr = [ "user_id" => $tokedata['uid'],  "block_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function duplicate(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokedata = $this->getJwtTokenData($headers);

    $org_id = $this->getID($args);
    $status = 500;
    $success = false;
    
    if ($org_id) {
      $userId = $this->getUserId($tokedata['uid']);
      $uid = $this->getUid();
      $org = R::load('blocks', $org_id);
      $newName = $this->duplicateBlockName($org->name);
      $newAlias = $this->duplicateBlockAlias($org->alias);
      $currentDate = date('Y-m-d H:i:s');
      $rb = R::dispense('blocks');
      $rb->uid = $uid;
      $rb->name = $newName;
      $rb->alias = $newAlias;
      $rb->content = $org->content;
      $rb->parent_id = 0;
      $rb->version_no = 0;
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      $success = true;
      $status = 201;
      $type = "BLOCK_DUPLICATED";
      $logArr = [ "user_id" => $tokedata['uid'], "new_name" => $newName, "new_alias" => $newAlias ];
      $resArr = ["created_date" => $currentDate, "modified_date" => $currentDate, "uid" => $uid, "name" => $newName, "alias" => $newAlias ]; 

    } else{
      $status = 200;
      $type = "BLOCK_NOT_DUPLICATED";
      $logArr = [ "user_id" => $tokedata['uid'],  "duplicated_from_id" => $args['uid'] ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function duplicateBlockName($name, $count = 0){
    $newName = $name.' Copy'.(($count == 0) ? '' : ' '.$count);
    $rb  = R::findOne( 'blocks', ' name LIKE ? ', [$newName]); 
    if (empty($rb->id)) {
      return $newName;
    } else {
      $count = $count + 1;
      return $this->duplicateBlockName($name, $count);
    }
  }

  private function duplicateBlockAlias($alias, $count = 0){
    $newAlias = $alias.'-copy'.(($count == 0) ? '' : '-'.$count);
    $rb  = R::findOne( 'blocks', ' alias LIKE ? ', [$newAlias]); 
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
      $rb  = R::findOne( 'blocks', 'name LIKE ? AND id != ?', [$name, $id]); 
    } else {
      $rb  = R::findOne( 'blocks', 'name LIKE ?', [$name]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "BLOCK_NAME_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "BLOCK_NAME_FOUND";
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
      $rb  = R::findOne( 'blocks', 'alias LIKE ? AND id != ?', [$alias, $id]); 
    } else {
      $rb  = R::findOne( 'blocks', 'alias LIKE ?', [$alias]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "BLOCK_ALIAS_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "BLOCK_ALIAS_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('blocks', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function  vBlock($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['name'])) {
      $param['name'] = [
        "value" => [
          "value" => isset($data['name'])? $data['name'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Name is required",
          "isBlockNameExist" => "Name already exist"
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
          "isBlockAliasExist" => "Alias already exist"
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

    return $this->validateData($param);
  }
}