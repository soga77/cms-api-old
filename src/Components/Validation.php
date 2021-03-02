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

  public function validateData (array $param) {
    $rArr = [];    
    foreach ($param as $dKey => $dParam) {
      $mArr = [];
      if (isset($dParam['match'])) {
        if ($dParam['match'] !== $dParam['value']) {
          foreach ($dParam['type'] as $tKey => $tParam) {
            if ($tKey == 'isNotMatch') {
              array_push($mArr, $tParam);
            }
            elseif ($this->$tKey($dParam['value'])) {
              array_push($mArr, $tParam);
            }         
          }
        }
      }
      else {
        foreach ($dParam['type'] as $tKey => $tParam) {
          if ($this->$tKey($dParam['value'])) {
            array_push($mArr, $tParam);
          }        
        }
      }
      if (!empty($mArr)) {
        $rArr[$dKey] = $mArr;
      }
    }
    $object = json_decode (json_encode($rArr, JSON_PRETTY_PRINT), FALSE); 
    return $object;
  }

  private function isEmpty($value) {
    return (empty($value)) ? true : false;
  }
  private function isNotEmail($value) {
    return (!preg_match(self::$emailReg, $value)) ? true : false;
  }
  private function isNotSlug($value) {
    return (!empty($value) && !preg_match(self::$slugReg, $value)) ? true : false;
  }  
  private function isNotWeakPwd($value) {
    return (!preg_match(self::$weakPwd, $value)) ? true : false;
  }
  private function isNotStrongPwd($value) {
    return (!preg_match(self::$strongPwd, $value)) ? true : false;
  }
  private function isNotMediumPwd($value) {
    return (!preg_match(self::$mediumPwd, $value)) ? true : false;
  }
  private function isNotEmailExist($value) {
    $rb  = R::findOne( "users", "email = ?", [$value]);
    return (!empty($rb->id)) ? true : false;
  }  
  private function isPageSlugExist($value) {
    $rb  = R::findOne( "pages", "slug = ?", [$value]);
    return (!empty($rb->id)) ? true : false;
  } 
  private function isEmailAliasExist($value) {
    $rb  = R::findOne( "emailtemplates", "alias = ?", [$value]);
    return (!empty($rb->id)) ? true : false;
  } 
  private function isEmailNameExist($value) {
    $rb  = R::findOne( 'emailtemplates', ' name LIKE ? ', [$value]); 
    return (!empty($rb->id)) ? true : false;
  }
  private function isModuleAliasExist($value) {
    $rb  = R::findOne( 'modules', ' alias LIKE ? ', [$value]); 
    return (!empty($rb->id)) ? true : false;
  }

}