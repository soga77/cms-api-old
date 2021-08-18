<?php
namespace App\Components;

use \RedBeanPHP\R;

class Validation
{
  private static $emailReg = "/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/"; 
  private static $weakPwd = "/^.{8,}$/";
  private static $mediumPwd = "/^(?=.*[0-9]).{8,20}$/";
  private static $strongPwd = "/^(?=.*[!@#$%^&*-])(?=.*[0-9])(?=.*[A-Z]).{8,20}$/";  
  private static $slugReg = "/^[a-z0-9]+(-?[a-z0-9]+)*$/i";
  private static $aliasReg = "/^[a-z0-9]+(-?[a-z0-9]+)*$/";  

  public function validateData (array $param) {
    $rArr = [];    
    foreach ($param as $dKey => $dParam) {
      $mArr = [];
      foreach ($dParam['type'] as $tKey => $tParam) {
        if ($this->$tKey($dParam['value'])) {
          array_push($mArr, $tParam);
        }        
      }
      
      if (!empty($mArr)) {
        $rArr[$dKey] = $mArr;
      }
    }
    $object = json_decode (json_encode($rArr, JSON_PRETTY_PRINT), FALSE); 
    return $object;
  }

  private function isNotMatch($value) {    
    return ($value['value'] !== $value['match']) ? true : false;
  }
  private function isEmpty($value) {
    return (empty($value['value'])) ? true : false;
  }
  private function isNotEmail($value) {
    return (!preg_match(self::$emailReg, $value['value'])) ? true : false;
  }
  private function isNotSlug($value) {
    return (!empty($value['value']) && !preg_match(self::$slugReg, $value['value'])) ? true : false;
  }  
  private function isNotAlias($value) {
    return (!empty($value['value']) && !preg_match(self::$aliasReg, $value['value'])) ? true : false;
  }  
  private function isNotWeakPwd($value) {
    return (!preg_match(self::$weakPwd, $value['value'])) ? true : false;
  }
  private function isNotStrongPwd($value) {
    return (!preg_match(self::$strongPwd, $value['value'])) ? true : false;
  }
  private function isNotMediumPwd($value) {
    return (!preg_match(self::$mediumPwd, $value['value'])) ? true : false;
  }
  private function isEmailExist($value) {
    $rb  = R::findOne( "users", "email = ?", [$value['value']]);
    return (!empty($rb->id)) ? true : false;
  }  
  private function isPageSlugExist($value) {
    $rb  = R::findOne( "pages", "slug = ?", [$value['value']]);
    return (!empty($rb->id)) ? true : false;
  }
  private function isEmailNameExist($value) {
    $rb  = R::findOne( 'emailtemplates', ' name LIKE ? ', [$value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isModuleNameExist($value) {
    $rb  = R::findOne( 'modules', ' name LIKE ? ', [$value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isModuleAliasExist($value) {
    $rb  = R::findOne( 'modules', ' alias LIKE ? ', [$value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isBlockNameExist($value) {
    $rb  = R::findOne( 'blocks', ' name LIKE ? ', [$value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isBlockAliasExist($value) {
    $rb  = R::findOne( 'blocks', ' alias LIKE ? ', [$value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isPermissionAliasExist($value) {
    $rb  = R::findOne( 'permissions', ' module_id = ? AND alias LIKE ? ', [$value['id'], $value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isNotificationAliasExist($value) {
    $rb  = R::findOne( 'notifications', ' module_id = ? AND alias LIKE ? ', [$value['id'], $value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isRoleNameExist($value) {
    $rb  = R::findOne( 'roles', ' name LIKE ? ', [$value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isRoleAliasExist($value) {
    $rb  = R::findOne( 'roles', ' alias LIKE ? ', [$value['value']]); 
    return (!empty($rb->id)) ? true : false;
  }

}