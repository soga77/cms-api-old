<?php
namespace App\Components;

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class JwtToken
{
  private $secret;
  private $encHash;

  public function __construct($settings)
  {
    $this->secret = $settings['secret'];
    $this->algorithm = 'HS256';
  }

  public function getJwtToken($data) {
    $now = time();
    $future = $now + (1440 * 60);
    // $jti = bin2hex(random_bytes(5));
    $jti = bin2hex(openssl_random_pseudo_bytes(16));
    $iss = $_SERVER['SERVER_NAME'];
    // $data = [
    //   "uid" => "sfdgsdfgsdfgsdfgsdfg",
    //   "name" => "David"
    // ];

    $payload = [
      'jti' => $jti,
      'iss' => $iss,
      "iat" => $now,
      "exp" => $future,
      "data" => $data
    ];

    return JWT::encode($payload, $this->secret, $this->algorithm);
  }

  public function getJwtData($token) {
    $user = JWT::decode($token, $this->secret, [$this->algorithm]);
    return get_object_vars($user->data);
  }
}