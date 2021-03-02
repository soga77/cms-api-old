<?php
namespace App\Components;

use Firebase\JWT\JWT;

class JwtToken
{
  private $secret;

  public function __construct($settings)
  {
    $this->secret = $settings['secret'];
  }

  public function getJwtToken($data) {
    $now = time();
    $future = $now + (60 * 60);
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

    return JWT::encode($payload, $this->secret, 'HS256');
  }
}