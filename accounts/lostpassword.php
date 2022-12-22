<?php
session_start();
include "../config/security.php";
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
Enter your account email<br>
<form action="lostpassword.php" method="post">
    Email: <input type="email" name="email" required><br>
    <?php
    if($captchaType == 1) {
        echo '<br /><img style="border: 1px solid black" src="../tools/cap.php"><br /><input name="captcha" placeholder="Enter captcha here..." required/><br /><br />';
    }
    if($captchaType == 2) {
        echo '<br /><div class="h-captcha" data-sitekey="'.$hcaptcha_key.'"></div><br /><script src="https://hcaptcha.com/1/api.js" async defer></script>';
    }
    if($captchaType == 3) {
        echo '<br /><div class="g-recaptcha" data-sitekey="'.$recaptcha_key.'"></div><script src="https://www.google.com/recaptcha/api.js"></script><br />';
    }
    ?>
    <input type="submit" value="Reset"></form>
<?php
include "../incl/lib/connection.php";
include "../incl/lib/mail.php";
$email = htmlspecialchars($_POST["email"]);
$cv = 0;
if($captchaType == 1) {
    $captcha = $_SESSION["captcha"];
    if($_POST["captcha"] == $captcha) {
        $cv = 1;
    } else {
        if($_POST) {
            exit("Captcha failed");
        }
    }
}
if($captchaType == 2) {
    $data = array(
        'secret' => $hcaptcha_secret,
        'response' => $_POST['h-captcha-response']
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://hcaptcha.com/siteverify");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $responseData = json_decode($response);
    if($responseData->success) {
        $cv = 1;
    } else {
        if($_POST) {
            exit("Captcha failed");
        }
    }
}
if($captchaType == 3) {
    function getIPAddress() {  
        if($_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
          return $ip;  
     }  
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $params = [
        'secret' => $recaptcha_secret, // Секретный ключ
        'response' => $_POST["g-recaptcha-response"], // Токин
        'remoteip' => getIPAddress(), // IP-адрес пользователя
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if(json_decode($response, true)["success"] == "1") {
        $cv = 1;
    } else {
        if($_POST) {
            exit("Captcha failed");
        }
    }
}
if(filter_var($email, FILTER_VALIDATE_EMAIL) && $cv == 1) {
    $query = $db->prepare("SELECT * FROM accounts WHERE email = :email");
    $query->execute([':email' => $email]);
    if ($query->rowCount() > 0) {
        $query = $db->prepare("SELECT accountID FROM accounts WHERE email = :email");
        $query->execute([':email' => $email]);
        $accountID = $query->fetchColumn();
        function generate($how_long) {
            $length = $how_long;
            $chars = 'abdefhiknrstyzABDEFGHKNQRSTYZ23456789';
            $numChars = strlen($chars);
            $string = '';
            for ($i = 0; $i < $length; $i++) {
                $string .= substr($chars, rand(1, $numChars) - 1, 1);
            }
            return $string;
        }
        $pass1 = generate(8);
        $token = generate(8);
        $pass = password_hash($pass1, PASSWORD_DEFAULT);
        $query = $db->prepare("INSERT INTO reset (acc, password, token)
        VALUES (:acc, :password, :token)");
        $query->execute([':acc' => $accountID, ':password' => $pass, ':token' => $token]);
        require("mail/PHPMailerAutoload.php");
        $mail = new PHPMailer;
        $mail->CharSet = 'utf-8';

        $mail->isSMTP();
        $mail->Host = $smtp;
        $mail->SMTPAuth = true;
        $mail->Username = $mail_server;
        $mail->Password = $mail_server_password; 
        $mail->SMTPSecure = $mail_type;
        $mail->Port = $smtp_port;

        $mail->setFrom($mail_server);
        $mail->addAddress("$email"); 
        $mail->isHTML(true);
        $mail->Subject = 'GDPS password reseting';
        $mail->Body    = "<h1 align=center>Hello $userName</h1><p align=center>Update your GDPS account password to $pass1 by going to link down:</p>
        <p align=center><a href='$url_reset?token=$token' 'style=color:blue;text-decoration:none'>Update your account password</a></p>
        <p align=center>Can not open link? $url_reset?token=$token</p>";
        $mail->AltBody = '';
        if($mail->send()) {
            echo "1";
        } else {
            echo "-1";
        }
    } else {
        echo "Email not found";
    }
} else {
    if(empty($_POST["email"])) {

    } else {
        echo "Email invalid";
    }
}

?>
