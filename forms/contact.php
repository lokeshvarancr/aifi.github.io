<?php
// Include PHPMailer library files
require '../assets/vendor/PHPMailer-master/src/Exception.php';
require '../assets/vendor/PHPMailer-master/src/PHPMailer.php';
require '../assets/vendor/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$response = array('status' => '', 'message' => '');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $messageContent = $_POST['message'];
    $honeypot = $_POST['first_name'];
    $privacy = $_POST['privacy'];
    $resume = $_FILES['resume'];
    $recaptchaResponse = $_POST['recaptcha_response'];
    $recaptchaSecretKey = '6LcVkQgqAAAAAOH5KnF5d2q6Dn2b0UxFTDF5mIdv'; // Ensure this is the correct secret key

    // Honeypot check
    if (!empty($honeypot)) {
        $response['status'] = 'error';
        $response['message'] = 'Spam detected.';
        echo json_encode($response);
        exit;
    }

    // Privacy policy check
    if ($privacy != 'accept') {
        $response['status'] = 'error';
        $response['message'] = 'Please accept our terms of service and privacy policy.';
        echo json_encode($response);
        exit;
    }

    // Verify reCAPTCHA v3
    $recaptchaVerification = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecretKey}&response={$recaptchaResponse}");
    if ($recaptchaVerification === false) {
        $response['status'] = 'error';
        $response['message'] = 'Could not verify reCAPTCHA. Please try again.';
        echo json_encode($response);
        exit;
    }

    $recaptchaKeys = json_decode($recaptchaVerification, true);

    // Log the entire reCAPTCHA response for debugging
    error_log(print_r($recaptchaKeys, true));

    // Check if reCAPTCHA verification failed
    if (!isset($recaptchaKeys['success']) || !$recaptchaKeys['success']) {
        $response['status'] = 'error';
        $response['message'] = 'reCAPTCHA verification failed. Please try again.';

        // Check for specific error codes
        if (isset($recaptchaKeys['error-codes'])) {
            if (in_array('invalid-input-secret', $recaptchaKeys['error-codes'])) {
                $response['message'] = 'Invalid reCAPTCHA secret key. Please check your configuration.';
            } elseif (in_array('timeout-or-duplicate', $recaptchaKeys['error-codes'])) {
                $response['message'] = 'reCAPTCHA verification failed due to timeout or duplicate token. Please refresh the page and try again.';
            }
        }

        $response['recaptcha'] = $recaptchaKeys;  // Include reCAPTCHA response for debugging
        echo json_encode($response);
        exit;
    }

    if ($recaptchaKeys['score'] < 0.5) {
        $response['status'] = 'error';
        $response['message'] = 'reCAPTCHA verification failed. Please try again.';
        $response['recaptcha'] = $recaptchaKeys;  // Include reCAPTCHA response for debugging
        echo json_encode($response);
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        // Enable SMTP debugging
        // $mail->SMTPDebug = 2; // Set to 0 for production use or 2 for debug output

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lokeshwaran3119@gmail.com'; // Your Gmail address
        $mail->Password   = 'nohc skis ctgu hfsd';            // Your App Password or Gmail password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($email, $name);
        $mail->addAddress('tamilantrends2000@gmail.com'); // Add your receiving email address

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Message from Contact Form';

        // Create the email body
        $body = "<p><strong>Name:</strong> {$name}</p>
                 <p><strong>Email:</strong> {$email}</p>
                 <p><strong>Message:</strong></p>
                 <p>{$messageContent}</p>";

        // Handle file attachment
        if ($resume['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $resume['tmp_name'];
            $file_name = $resume['name'];
            $file_size = $resume['size'];
            $file_type = $resume['type'];
            $file_error = $resume['error'];

            $body .= "<p><strong>File Attached:</strong> {$file_name}</p>";
            $mail->addAttachment($file_tmp_path, $file_name);
        } else {
            $body .= "<p>No file attached or there was an error uploading the file.</p>";
        }

        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        $response['status'] = 'success';
        $response['message'] = 'Message has been sent';
    } catch (Exception $e) {
        $response['status'] = 'error';
        $response['message'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }

    echo json_encode($response);
    exit; // Ensure script exits after sending the response
}
?>
