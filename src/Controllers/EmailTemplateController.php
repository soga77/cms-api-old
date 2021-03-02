<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class EmailTemplateController extends BaseController
{
  public function add(Request $request, Response $response) {
    $data = $request->getParsedBody();
    // Validate data
    $vResult = $this->vTemplate($data);

    // Add template
    if (empty($vResult)) {
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('emailtemplates');
      $rb->uid = $uid;
      $rb->name = $data['name'];
      $rb->subject = $data['subject'];
      $rb->content = $data['content'];
      $rb->keys = $data['keys'];
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $id = R::store($rb);
      $this->logger->info('Email template added', ['id' => $id, 'user_id' => 0]);
      $result = [ "success" => true, "response" => ["uid" => $uid, "name" => $data['name'], "subject" => $data['subject'], "created_date" => $currentDate, "modified_date" => $currentDate]];
    } 
    // Set validation error(s)
    else {
      $this->logger->info('Email template not added', ['validation' => $vResult]);
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
      $rb = R::load('emailtemplates', $id);
      $name = strtolower($rb->name);
      $subject = strtolower($rb->subject);
      $content = strtolower($rb->content);
      $keys = strtolower($rb->keys);
      $createDate = $rb->created_date;

      if (isset($data['name']) && strtolower($data['name']) !== $name) {
        $rb->name = $data['name'];
        array_push($cArr, 'name');
      }
      if (isset($data['subject']) && strtolower($data['subject']) !== $subject) {
        $rb->subject = $data['subject'];
        array_push($cArr, 'subject');
      }
      if (isset($data['content']) && strtolower($data['content']) !== $content) {
        $rb->content = $data['content'];
        array_push($cArr, 'content');
      }
      if (isset($data['keys']) && strtolower($data['keys']) !== $keys) {
        $rb->keys = $data['keys'];
        array_push($cArr, 'keys');
      }
      // Validate data
      $vResult = $this->vTemplate($data, $cArr);

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          R::store($rb);
          $this->logger->info('Email template updated', ['id' => $id, 'user_id' => 0, 'changes' => implode(", ", $cArr)]);
          $result = [ "success" => true, "response" => ["uid" => $data['uid'] ,"name" => $data['name'], "subject" => $data['subject'], "created_date" => $createDate, "modified_date" => $currentDate], "changes" => $cArr];
        } else {
          $this->logger->info('Email template not updated no changes',  ['id' => $id, 'user_id' => 0]);
          $result = [ "success" => false, "response" => [ "type" => "NO_CHANGES_MADE", "description" => "No changes were made" ]];
        }
      } else {
        $this->logger->info('Email template not updated validation error',  ['id' => $id, 'user_id' => 0, 'validation' => $vResult]);
        $result = [ "success" => false, "response" => [ "type" => "VALIDATION_ERROR", "description" => $vResult ]];
      }      
    } else {
      $this->logger->info('Email template invalid request', ['user_id' => 0]);
      $result = [ "success" => false, "response" => [ "type" => "INVALID_REQUEST", "description" => "Invalid request recieved"]];
    }       
    // Return response
    return $this->respondWithData($response,$result);
  }

  public function copy(Request $request, Response $response, $args) {
    $oid = $this->getID($args);
    if ($oid) {
      $uid = $this->getUid();
      $oRb = R::load('emailtemplates', $oid);   
      $newName = $this->duplicateTemplateName($oRb->name);   
      $currentDate = date('Y-m-d H:i:s');
      $rb = R::dispense('emailtemplates');
      $rb->uid = $uid;
      $rb->name = $newName;
      $rb->subject = $oRb->subject;
      $rb->content = $oRb->content;
      $rb->keys = $oRb->keys;
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $id = R::store($rb);
      $this->logger->info('Email template duplicated', ['id' => $id, 'user_id' => 0]);
      $result = [ "success" => true, "response" => ["created_date" => $currentDate, "modified_date" => $currentDate, "subject" => $oRb->subject, "uid" => $uid, "name" => $newName ]];
    } else{
      $this->logger->info('Email template not duplicated', ['uid' => $args['uid'], 'user_id' => 0]);
      $result = [ "success" => false, "response" => ["type" => "RECORD_NOT_DUPLICATED", "description" => "Email template could not be duplicated" ]]; 
    }    
    // Return response
    return $this->respondWithData($response,$result);
  }

  public function delete(Request $request, Response $response, $args) {
    $id = $this->getID($args);
    if ($id) {
      $rb = R::load('emailtemplates', $id);
      $name = $rb->name;
      R::trash( $rb );
      $this->logger->info('Email template deleted', ['id' => $id, 'user_id' => 0]);
      $result = [ "success" => true, "response" => [ "name" => $name ]]; 
    } else{
      $this->logger->info('Email template not deleted', ['uid' => $args['uid'], 'user_id' => 0]);
      $result = [ "success" => false, "response" => [ "type" => "RECORD_NOT_DELETED", "description" => "Email template could not be deleted" ]]; 
    }    
    // Return response
    return $this->respondWithData($response,$result);
  }

  public function exist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $name = $data['name'];

    if ($id) {
      $rb  = R::findOne( 'emailtemplates', ' name LIKE ? AND id != ?  ', [$name, $id]); 
    } else {
      $rb  = R::findOne( 'emailtemplates', ' name LIKE ? ', [$name]); 
    }

    $result = ["success" => (!empty($rb->id)) ? true : false];
    // Return response
    return $this->respondWithData($response,$result);
  }

  public function view(Request $request, Response $response, $args) {
    $id = $this->getID($args);
    if ($id) {
      $rb = R::load('emailtemplates', $id);
      $rb['keys'] = explode(",", $rb['keys']);
      unset($rb['id']);
      $this->logger->info('Email template retrieved', ['id' => $id, 'user_id' => 0]);
      $result = [ "success" => true, "response" => $rb ]; 
    } else{
      $this->logger->info('Email template not found', ['uid' => $args['uid'], 'user_id' => 0]);
      $result = [ "success" => false, "response" => [ "type" => "RECORD_NOT_FOUND", "description" => "Email template record not found" ]]; 
    }    
    // Return response
    return $this->respondWithData($response,$result);
  }  

  public function list(Request $request, Response $response) {
    $templates = R::findAll('emailtemplates', ' order by name asc ');
    $row = [];
    foreach ($templates as $template) {
      $row[] = [
        "uid" => $template->uid,
        "name" => $template->name,
        "subject" => $template->subject,
        //"content" => $template->content,
        //"keys" => $template->keys,
        // "created_date" => $template->created_date,
        "modified_date" => $template->modified_date
      ];
    }    
    //$status = empty($row) ? 'empty' : 'success';
    if (empty($row)) {
      $result = [ "success" => false, "response" => [ "type" => "RECORDS_NOT_FOUND", "description" => "Email template list not found" ]]; 
      
    } else {
      $result = [ "success" => true, "response" => $row ]; 
    }
    
    $this->logger->info('Email template list retrieved', ['user_id' => 0]);
    return $this->respondWithData($response,$result);
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('emailtemplates', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function duplicateTemplateName($name, $count = 0){
    $newName = $name.' copy'.(($count == 0) ? '' : ' '.$count);
    $rb  = R::findOne( 'emailtemplates', ' name LIKE ? ', [$newName]); 
    if (empty($rb->id)) {
      return $newName;
    } else {
      $count = $count + 1;
      return $this->duplicateTemplateName($name, $count);
    }
  }

  private function  vTemplate($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['name'])) {
      $param['name'] = [
        "value" => isset($data['name'])? $data['name'] : '',
        "type" => [ 
          "isEmpty" => "Template name is required",
          "isEmailNameExist" => "Template name already exist"
        ]
      ];
    } else {
      $param['name'] = [
        "value" => isset($data['name'])? $data['name'] : '',
        "type" => [ 
          "isEmpty" => "Template name is required"
        ]
      ];
    }    
    $param['subject'] = [
      "value" => isset($data['subject'])? $data['subject'] : '',
      "type" => [ "isEmpty" => "Subject is required" ]
    ];
    $param['content'] = [
      "value" => isset($data['content'])? $data['content'] : '',
      "type" => [ "isEmpty" => "Content is required"]
    ];
    $param['keys'] = [
      "value" => isset($data['keys'])? $data['keys'] : '',
      "type" => [ "isEmpty" => "Keys is required"]
    ];
    
    return $this->validateData($param);
  }
}