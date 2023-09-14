<?php 

  require('../admin/inc/db_config.php');
  require('../admin/inc/essentials.php');
  require("../inc/sendgrid/sendgrid-php.php");

  date_default_timezone_set("Asia/Kolkata");
  //$rdir = str_replace("\\", "/", __DIR__);                    //Root Dir
  // require $rdir.'/PHPMailer/src/Exception.php';
  // require $rdir.'/PHPMailer/src/PHPMailer.php';
  // require $rdir.'/PHPMailer/src/SMTP.php';
  
  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\SMTP;
  use PHPMailer\PHPMailer\Exception;

  function send_mail($uemail,$token,$type)
  {

    if($type == "email_confirmation"){
      $page = 'email_confirm.php';
      $subject = "Account Verification Link";
      $content = "confirm your email";
    }
    else{
      $page = 'index.php';
      $subject = "Account Reset Link";
      $content = "reset your account";
    }

    $email = new \SendGrid\Mail\Mail(); 
    
    $email->setFrom("guesthouse.lnmiit@gmail.com","Guest HOuse LNMIIT");
    $email->setSubject("Account Verification Link");

    $email->addTo($uemail);

    $email->addContent(
        "text/html", 
        "
          Click the link to $content: <br>
          <a href='".SITE_URL."$page?$type&email=$uemail&token=$token"."'>
            CLICK ME
          </a>
        "
    );

    $sendgrid = new \SendGrid(SENDGRID_API_KEY);

    try{
      $sendgrid->send($email);
      return 1;
    }
    catch (Exception $e){
      return 0;
    }
  }
  
  // function sendMail($email, $code){
  //   require ('../PHPMailer/src/Exception.php');
  //   require ('../PHPMailer/src/PHPMailer.php');
  //   require ('../PHPMailer/src/SMTP.php');

  //   $mail =new PHPMailer(true);
    
  //   try {
  //     //Server settings
  //                        //Enable verbose debug output
  //     $mail->isSMTP();                                            //Send using SMTP
  //     $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
  //     $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
  //     $mail->Username   = 'guesthouse.lnmiit@gmail.com';                     //SMTP username
  //     $mail->Password   = 'qwertyuiop@123';                               //SMTP password
  //     $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            //Enable implicit TLS encryption
  //     $mail->Port       = 587;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
  
  //     //Recipients
  //     $mail->setFrom('guesthouse.lnmiit@gmail.com', 'Guest House LNMIIT');
  //     $mail->addAddress($email);     //Add a recipient
  
  //     //Content
  //     $mail->isHTML(true);                                  //Set email format to HTML
  //     $mail->Subject = 'Email Verification';
  //     $mail->Body    = "Thanks for Registration. Click on the link to verify the email address <br> <a href='http://localhost/emailverify/verify.php?email=$email & code=$code'>Click Here</a>";
      
  
  //     $mail->send();
  //     return true;
  // } catch (Exception $e) {
  //     return false;
  //     echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
  // }

  // }
  
  // function validating($phone){
    
  //   }

  if(isset($_POST['register']))
  {
    $data = filteration($_POST);

    // match password and confirm password field

    if($data['pass'] != $data['cpass']) {
      echo 'pass_mismatch';
      exit;
    }

    //check if phone number is valid or not
   
    if(!preg_match('/^[0-9]{10}+$/', $data['phonenum'])) {
      echo "Invalid Phone Number";
      
      
      exit;
      }

    // check user exists or not

    $u_exist = select("SELECT * FROM `user_cred` WHERE `email` = ? OR `phonenum` = ? LIMIT 1",
      [$data['email'],$data['phonenum']],"ss");

    if(mysqli_num_rows($u_exist)!=0){
      $u_exist_fetch = mysqli_fetch_assoc($u_exist);
      echo ($u_exist_fetch['email'] == $data['email']) ? 'email_already' : 'phone_already';
      exit;
    }

    // upload user image to server

    $img = uploadUserImage($_FILES['profile']);

    if($img == 'inv_img'){
      echo 'inv_img';
      exit;
    }
    else if($img == 'upd_failed'){
      echo 'upd_failed';
      exit;
    }

    // send confirmation link to user's email

    $token = bin2hex(random_bytes(16));

    if(!send_mail($data['email'],$token,"email_confirmation")){
      echo 'mail_failed';
      exit;
    }
    // if(!sendMail($data['email'], $token)){
    //   echo 'mail_failed';
    //   exit;
    // }

    // $enc_pass = password_hash($data['pass'],PASSWORD_BCRYPT);
    $enc_pass=$data['pass'];

    $query = "INSERT INTO `user_cred`(`name`, `email`, `address`, `phonenum`, `pincode`, `dob`,
      `profile`, `password`, `token`) VALUES (?,?,?,?,?,?,?,?,?)";
      
    $values = [$data['name'],$data['email'],$data['address'],$data['phonenum'],$data['pincode'],$data['dob'],
      $img,$enc_pass,$token];

    if(insert($query,$values,'sssssssss')){
      echo 1;
    }
    else{
      echo 'ins_failed';
    }

  }

  

  if(isset($_POST['login']))
  {
    $data = filteration($_POST);

    $u_exist = select("SELECT * FROM `user_cred` WHERE `email`=? OR `phonenum`=? LIMIT 1",
    [$data['email_mob'],$data['email_mob']],"ss");

    if(mysqli_num_rows($u_exist)==0){
      echo 'inv_email_mob';
    }
    else{
      $u_fetch = mysqli_fetch_assoc($u_exist);
      if($u_fetch['is_verified']==0){
        echo 'not_verified';
      }
      else if($u_fetch['status']==0){
        echo 'inactive';
      }
      else{
        // if(!password_verify($data['pass'],$u_fetch['password'])){
        //   echo 'invalid_pass';
        // }
        if($data['pass']!=$u_fetch['password']){
          echo 'invalid_pass';
        }
        else{
          session_start();
          $_SESSION['login'] = true;
          $_SESSION['uId'] = $u_fetch['id'];
          $_SESSION['uName'] = $u_fetch['name'];
          $_SESSION['uPic'] = $u_fetch['profile'];
          $_SESSION['uPhone'] = $u_fetch['phonenum'];
          echo 1;
        }
      }
    }
  }

  if(isset($_POST['forgot_pass']))
  {
    $data = filteration($_POST);
    
    $u_exist = select("SELECT * FROM `user_cred` WHERE `email`=? LIMIT 1", [$data['email']], "s");

    if(mysqli_num_rows($u_exist)==0){
      echo 'inv_email';
    }
    else
    {
      $u_fetch = mysqli_fetch_assoc($u_exist);
      if($u_fetch['is_verified']==0){
        echo 'not_verified';
      }
      else if($u_fetch['status']==0){
        echo 'inactive';
      }
      else{
        // send reset link to email
        $token = bin2hex(random_bytes(16));

        if(!send_mail($data['email'],$token,'account_recovery')){
          echo 'mail_failed';
        }
        else
        {
          $date = date("Y-m-d");

          $query = mysqli_query($con,"UPDATE `user_cred` SET `token`='$token', `t_expire`='$date' 
            WHERE `id`='$u_fetch[id]'");

          if($query){
            echo 1;
          }
          else{
            echo 'upd_failed';
          }
        }
      }
    }

  }

  if(isset($_POST['recover_user']))
  {
    $data = filteration($_POST);
    
    $enc_pass = password_hash($data['pass'],PASSWORD_BCRYPT);

    $query = "UPDATE `user_cred` SET `password`=?, `token`=?, `t_expire`=? 
      WHERE `email`=? AND `token`=?";

    $values = [$enc_pass,null,null,$data['email'],$data['token']];

    if(update($query,$values,'sssss'))
    {
      echo 1;
    }
    else{
      echo 'failed';
    }
  }
  
?>