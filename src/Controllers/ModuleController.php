<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class ModuleController extends BaseController 
{
  public function add(Request $request, Response $response) {
    $data = $request->getParsedBody();
    // Validate data
    $vResult = $this->vModule($data);

    // Add Module
    if (empty($vResult)) {
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('modules');
      $rb->uid = $uid;
      $rb->name = $data['name'];
      $rb->alias = $data['alias'];
      $rb->description = $data['description'];
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $id = R::store($rb);
      $this->logger->info('Module added', ['id' => $id, 'user_id' => 0]);
      $result = [ "success" => true, "response" => ["uid" => $uid, "name" => $data['name'], "alias" => $data['alias'], "description" => $data['description'], "created_date" => $currentDate, "modified_date" => $currentDate]];
    } 
    // Set validation error(s)
    else {
      $this->logger->info('Module not added', ['validation' => $vResult]);
      $result = [ "success" => false, "response" => [ "type" => "VALIDATION_ERROR", "description" => $vResult ]];
    }
    // Return response
    return $this->respondWithData($response,$result);
  }

  public function edit(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); //get record to update
    
    // Update record
    if ($id) {
      $cArr = [];
      $rb = R::load('modules', $id);
      $name = strtolower($rb->name);
      $subject = strtolower($rb->description);
      $createDate = $rb->create_date;

      if (isset($data['name']) && strtolower($data['name']) !== $name) {
        $rb->name = $data['name'];
        array_push($cArr, 'name');
      }
      if (isset($data['description']) && strtolower($data['description']) !== $subject) {
        $rb->subject = $data['description'];
        array_push($cArr, 'description');
      }
      // Validate data
      $vResult = $this->vModule($data, $cArr);

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $updateDate = date('Y-m-d H:i:s');
          $rb->update_date = $updateDate;
          R::store($rb);
          $this->logger->info('Module updated', ['id' => $id, 'user_id' => 0, 'changes' => implode(", ", $cArr)]);
          $result = [ "success" => true, "response" => ["uid" => $data['uid'] ,"name" => $data['name'], "subject" => $data['subject'], "create_date" => $createDate, "update_date" => $updateDate], "changes" => $cArr];
        } else {
          $this->logger->info('Module not updated no changes',  ['id' => $id, 'user_id' => 0]);
          $result = [ "success" => false, "response" => [ "type" => "NO_CHANGES_MADE", "description" => "No changes were made" ]];
        }
      } else {
        $this->logger->info('Module not updated validation error',  ['id' => $id, 'user_id' => 0, 'validation' => $vResult]);
        $result = [ "success" => false, "response" => [ "type" => "VALIDATION_ERROR", "description" => $vResult ]];
      }      
    } else {
      $this->logger->info('Module invalid request', ['user_id' => 0]);
      $result = [ "success" => false, "response" => [ "type" => "INVALID_REQUEST", "description" => "Invalid request recieved"]];
    }       
    // Return response
    return $this->respondWithData($response,$result);
  }

  public function list(Request $request, Response $response) {
    $templates = R::findAll('modules', ' order by name asc ');
    $row = [];
    foreach ($templates as $template) {
      $row[] = [
        "uid" => $template->uid,
        "name" => $template->name,
        "description" => $template->description,
        "create_date" => $template->create_date,
        "update_date" => $template->update_date
      ];
    }    
    if (empty($row)) {
      $result = [ "success" => false, "response" => [ "type" => "RECORDS_NOT_FOUND", "description" => "Modules list not found" ]]; 
      
    } else {
      $result = [ "success" => true, "response" => $row ]; 
    }
    
    $this->logger->info('Modules list retrieved', ['user_id' => 0]);
    return $this->respondWithData($response,$result);
  }

  public function delete(Request $request, Response $response, $args) {
    $id = $this->getID($args);
    if ($id) {
      $rb = R::load('modules', $id);
      $name = $rb->name;
      R::trash( $rb );
      $this->logger->info('Module deleted', ['id' => $id, 'user_id' => 0]);
      $result = [ "success" => true, "response" => [ "name" => $name ]]; 
    } else{
      $this->logger->info('Module not deleted', ['uid' => $args['uid'], 'user_id' => 0]);
      $result = [ "success" => false, "response" => [ "type" => "RECORD_NOT_DELETED", "description" => "Module could not be deleted" ]]; 
    }    
    // Return response
    return $this->respondWithData($response,$result);
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('modules', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function  vModule($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['alias'])) {
      $param['alias'] = [
        "value" => isset($data['alias'])? $data['alias'] : '',
        "type" => [ 
          "isEmpty" => "Alias is required",
          "isNotSlug" => "Invalid Alias",
          "isModuleAliasExist" => "Alias already exist"
        ]
      ];
    } else {
      $param['alias'] = [
        "value" => isset($data['alias'])? $data['alias'] : '',
        "type" => [ 
          "isEmpty" => "Alias is required"
        ]
      ];
    }    
    $param['name'] = [
      "value" => isset($data['name'])? $data['name'] : '',
      "type" => [ "isEmpty" => "name is required" ]
    ];
    $param['description'] = [
      "value" => isset($data['description'])? $data['description'] : '',
      "type" => [ "isEmpty" => "Description is required" ]
    ];

    return $this->validateData($param);
  }
}