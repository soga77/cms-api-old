<?php

namespace App\Middleware;

use DOMDocument;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strstr($contentType, 'application/json')) {
          $getContents = json_decode(file_get_contents('php://input'), true);
          if (json_last_error() === JSON_ERROR_NONE) {
              $cleanContent = $this->sanitizeJson($getContents);
              $request = $request->withParsedBody($cleanContent);
          }
        }
        return $handler->handle($request);
    }

    private function sanitizeJson (array $resArr) {
      $resArr = array_map('trim', $resArr);
      foreach ($resArr as $key => $value) {
        if(strpos($key, 'html_') === 0){
          $newArr[substr($key, 5)] = htmlspecialchars($value, ENT_QUOTES);
        }
        elseif (strpos($key, 'raw_') === 0) {
          $newArr[substr($key, 4)] = $value;
        } else {
          $newArr[$key] = filter_var($value,FILTER_SANITIZE_STRING);
        }
      }  
      return $newArr;
    }

    private function removeScript($html){
      $dom = new DOMDocument();
      $dom->loadHTML($html,LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NOWARNING);
      $script = $dom->getElementsByTagName('script');
  
      $remove = [];
      foreach($script as $item){
          $remove[] = $item;
      }
  
      foreach ($remove as $item){
          $item->parentNode->removeChild($item);
      }
  
      $html = $dom->saveHTML();
      //$html = preg_replace('/<!DOCTYPE.*?<html>.*?<body><p>/ims', '', $html);
      //$html = str_replace('</p></body></html>', '', $html);
      return $html;
  }
}