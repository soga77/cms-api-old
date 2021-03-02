<?php
namespace App\Components;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
  private $settings;

  public function __construct($settings)
  {
    $this->settings = $settings;
  }

  public function sendMail($param, $template) {
    $mail = new PHPMailer(true);

    try {
      //Server settings
      $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
      $mail->isSMTP();                                            // Send using SMTP
      $mail->Host       = $this->settings['host'];                    // Set the SMTP server to send through
      $mail->SMTPAuth   = $this->settings['smtpAuth'];                                    // Enable SMTP authentication
      $mail->Username   = $this->settings['username'];                    // SMTP username
      $mail->Password   = $this->settings['password'];                   // SMTP password
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
      $mail->Port       = $this->settings['port'];              // TCP port to connect to
  
      //Recipients
      $mail->setFrom($this->settings['fromEmail'], $this->settings['fromName']);
      $mail->addAddress($param['email'], $param['first_name']." ".$param['last_name']);     // Add a recipient
      //$mail->addAddress('ellen@example.com');               // Name is optional
      //$mail->addReplyTo('info@example.com', 'Information');
      //$mail->addCC('cc@example.com');
      //$mail->addBCC('bcc@example.com');
  
      // Attachments
      //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
      //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
  
      // Content
      $mail->isHTML(true);                                  // Set email format to HTML
      $mail->Subject = 'Here is the subject';
      $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
      $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
  
      $mail->send();
      return true;
    } catch (Exception $e) {
      return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
  }  
}