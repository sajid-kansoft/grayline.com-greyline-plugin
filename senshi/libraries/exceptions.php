<?php

class SenshiException extends Exception
{

    protected $type = "Senshi Exception";
    // path to the log file
    protected $log_file = 'log_exceptions.txt';
    protected $flags = array("CRITICAL", "DEBUG", "DEFAULT");
    protected $emailNotify = array('iona@palisis.com');
    protected $emailCritical = 'iona@palisis.com';
    protected $emailCriticalGL = 'operations@grayline.com';
    protected $emailFinance = 'finance@palisis.com';
    protected $emailPalisisNotifications = 'notifications@palisis.com';
    protected $fromEmail = "no-reply@grayline.com";
    protected $deubgMode = false;

    public function __construct($message, $flag = null)
    {
        error_log($message);
        
        $dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/{$this->log_file}";

        $folder = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/";

        // if not a directory, then create it
        if (!is_dir($folder)) {
            mkdir($folder, 0750, true);
        }

        if (defined('ENV') && ENV === 'test') {
            $message .= "\n\n *** NOT LIVE, STAGING SITE, TESTING SYSTEM ***";
        }

        //$message = "There was a file error";
        $ip = null;
        // open the log file for appending
        $fp = fopen($dir, (file_exists($dir)) ? 'a' : 'w');
        if ($fp) {
            //if ($fp = fopen($dir,'a')) {

            // construct the log message
            //$ip = $_SERVER[REMOTE_ADDR];
            //$ip = getHostByName(getHostName());
            $ip = $this->get_client_ip_env();
            $date = new DateTime("now", new DateTimeZone('Europe/London'));
            $log_msg = $date->format('Y-m-d H:i:s')." [$ip] ".
                " Message: $message\n";

            fwrite($fp, $log_msg);

            fclose($fp);
        }

        if (defined('MAIL_DEBUG')) {
            $this->deubgMode = MAIL_DEBUG;
        }

        if ($flag == $this->flags[0] || $flag == $this->flags[1] && $this->deubgMode === true) { 
            $this->emailAlert($message, $ip, $flag);
        }

        // call parent constructor
        parent::__construct($message);
    }

    // Function to get the client ip address
    public function get_client_ip_env()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
            } else {
                if (getenv('HTTP_X_FORWARDED')) {
                    $ipaddress = getenv('HTTP_X_FORWARDED');
                } else {
                    if (getenv('HTTP_FORWARDED_FOR')) {
                        $ipaddress = getenv('HTTP_FORWARDED_FOR');
                    } else {
                        if (getenv('HTTP_FORWARDED')) {
                            $ipaddress = getenv('HTTP_FORWARDED');
                        } else {
                            if (getenv('REMOTE_ADDR')) {
                                $ipaddress = getenv('REMOTE_ADDR');
                            } else {
                                $ipaddress = 'UNKNOWN';
                            }
                        }
                    }
                }
            }
        }

        return $ipaddress;
    }

    protected function emailAlert($message, $ip, $flag)
    {
        if ($flag == "CRITICAL") {
            $this->emailNotify[] = $this->emailCritical;
        }

        if ($this->deubgMode) {
             if (defined('ENV') && ENV === 'test') {
                $this->fromEmail = $this->emailCritical;
             }
        }


        $body = $message."\n\n";
        $body .= "\n\n\n--------------------------------------------------------------\n";
        $body .= date('l jS \of F Y h:i:s A')."\n";
        $body .= $ip."\n";
        $body .= "\n";

        $subject = "GLWW $flag: {$this->type}";
        $headers = 'From: '. $this->fromEmail . "\r\n" .
            'Reply-To: ' . $this->fromEmail . "\r\n";

        wp_mail($this->emailNotify, $subject, strip_tags($body), $headers);
        
        

        // $mh = Loader::helper('mail');
        // //$adminUserInfo = UserInfo::getByID(USER_SUPER_ID);
        // //$adminEmailAddress = $adminUserInfo->getUserEmail();
        // // also email will and iona if critical
        // if ($flag == "CRITICAL") {
        //     $this->emailNotify[] = $this->emailCritical;
        // }
        // foreach ($this->emailNotify as $email) {
        //     $mh->to(trim($email));
        // }
        // if ($this->deubgMode) {
        //     if (defined('ENV') && ENV === 'test') {
        //         $this->fromEmail = $this->emailCritical;
        //     }
        // }
        // $subject = "GLWW $flag: {$this->type}";
        // $body = $message."\n\n";
        // $body .= "\n\n\n--------------------------------------------------------------\n";
        // $body .= date('l jS \of F Y h:i:s A')."\n";
        // $body .= $ip."\n";
        // $body .= "\n";
        // $mh->setSubject($subject);
        // $mh->setBody($body);
        // //$mh->to($adminEmailAddress);
        // $mh->from($this->fromEmail);
        // $mh->sendMail();
    }

}

class TmtException extends SenshiException
{

    // path to the log file
    protected $type = "TMT Exception";
    protected $log_file = 'log_tmt_exceptions.txt';
    protected $flag = "PAYMENT PROBLEM";
    //protected $emailNotify = array('admin@senshi.digital', 'fail@trustmytravel.com');
    protected $emailNotify = array('will.plummer@trustmytravel.com');


    public function __construct($message, $flag = null)
    {
        if (defined('TMT_STAGE') && TMT_STAGE === true) {
            $this->deubgMode = true;
            // don't email TMT if in testing mode
            $this->emailNotify = [];
            // do email GLWW for net rates testing
            $this->emailNotify[] = $this->emailCriticalGL;
        }
        // call parent constructor
        // Making all TMT exceptions critical initially
        $flag = $flag ? $flag : $this->flag;
        // also email will and iona if critical
        if ($flag == "CRITICAL" && $this->deubgMode == false) {
            $this->emailNotify[] = $this->emailCriticalGL;
        }
        if ($this->deubgMode) {
            $message .= "\n\n *** NOT LIVE, TMT STAGE GATEWAY, TESTING SYSTEM ***";
        }
        parent::__construct($message, $flag);
    }

}

class PaymentException extends SenshiException
{

    protected $type = "Payment Exception";
    // path to the log file
    protected $log_file = 'log_payment_exceptions.txt';
    protected $flag = "CRITICAL";


    public function __construct($message, $flag = null)
    {
        // call parent constructor
        // Making all payment exceptions critical initially
        $flag = $flag ? $flag : $this->flag;
        parent::__construct($message, $flag);
    }

}

class AdyenPaymentException extends SenshiException
{

    protected $type = "Adyen Payment Exception";
    // path to the log file
    protected $log_file = 'log_adyen_exceptions.txt';
    protected $flag = "CRITICAL";


    public function __construct($message, $flag = null)
    {
        // Notify Palisis support (e.g. Adyen negative transaction warning)
        if (defined('ENV') && ENV === 'live') {
            // we're in production, so let's notify support helpdesk
            if (strpos($message, 'Could not find an acquirer account for the provided currency') !== false) {
                $flag = 'DEBUG';
            }
            else if ($message !== 'Payment details are not supported' ) {
                $this->emailNotify[] = $this->emailPalisisNotifications;
            }
        }
        // call parent constructor
        // Making all payment exceptions critical initially
        $flag = $flag ? $flag : $this->flag;
        parent::__construct($message, $flag);
    }

}


class AdyenWebhookException extends SenshiException
{

    protected $type = "Adyen Webhook Exception";
    // path to the log file
    protected $log_file = 'log_adyen_webhook_exceptions.txt';
    protected $flag = "CRITICAL";


    public function __construct($message, $flag = null)
    {
        // call parent constructor
        // Making all payment exceptions critical initially
        $flag = $flag ? $flag : $this->flag;
        parent::__construct($message, $flag);
    }

}

class RedirectSuccessException extends SenshiException
{

    protected $type = "Redirect Success Exception";
    // path to the log file
    protected $log_file = 'log_redirect_success_exceptions.txt';
    protected $flag = "DEBUG";
    protected $redirect_url;

    public function getRedirectUrl()
    {
        return $this->redirect_url;
    }


    public function __construct($message, $redirect_url = null, $flag = null)
    {
        // call parent constructor
        // Making all payment exceptions critical initially
        $flag = $flag ? $flag : $this->flag;
        $this->redirect_url = $redirect_url;
        parent::__construct($message, $flag);
    }

}

class RedirectException extends SenshiException
{

    protected $type = "Redirect Exception";
    // path to the log file
    protected $log_file = 'log_redirect_exceptions.txt';
    protected $flag = "DEBUG";
    protected $redirect_url;

    public function getRedirectUrl()
    {
        return $this->redirect_url;
    }


    public function __construct($message, $redirect_url = null, $flag = null)
    {
        // call parent constructor
        // Making all payment exceptions critical initially
        $flag = $flag ? $flag : $this->flag;
        $this->redirect_url = $redirect_url;
        parent::__construct($message, $flag);
    }

}

class InvoicePaymentException extends SenshiException
{

    protected $type = "Invoice Payment Exception";
    // path to the log file
    protected $log_file = 'log_invoice_payment_exceptions.txt';

    public function __construct($message, $flag = null)
    {
        // call parent constructor
        parent::__construct($message, $flag);
    }

}


class FileException extends SenshiException
{

    protected $type = "File Exception";
    // path to the log file
    protected $log_file = 'log_file_exceptions.txt';
    protected $flag = "CRITICAL";

    public function __construct($message, $flag = null)
    {
        // call parent constructor
        $flag = $flag ? $flag : $this->flag;
        parent::__construct($message, $flag);
    }

}

class InternalException extends SenshiException
{

    protected $type = "Internal Senshi Exception";
    // path to the log file
    protected $log_file = 'log_internal_exceptions.txt';
    protected $flag = "CRITICAL";

    public function __construct($message, $flag = null)
    {  
        // call parent constructor
        $flag = $flag ? $flag : $this->flag;
        parent::__construct($message, $flag);
    }

}

class FeefoException extends SenshiException
{

    protected $type = "Feefo Exception";
    // path to the log file
    protected $log_file = 'log_feefo_exceptions.txt';
    protected $flag = "CRITICAL";

    public function __construct($message, $flag = null)
    {
        // call parent constructor
        $flag = $flag ? $flag : $this->flag;
        parent::__construct($message, $flag);
    }

}

class TourcmsException extends SenshiException
{

    protected $type = "TourCMS Exception";
    // path to the log file
    protected $log_file = 'log_tourcms_exceptions.txt';

    public function __construct($message, $flag = null)
    {
        // demote some messages
        if ($message === 'EXPIRED+KEY+%28LASTS+1+HOUR%29+-+CHECK+AVAILABILITY+' || $message === 'TourCMS API Error: EXPIRED BOOKING KEY | POST /c/booking/new/start.xml') {
            $flag = 'DEBUG';
        }
        // call parent constructor
        parent::__construct($message, $flag);
    }

}


class PublicException extends Exception
{

    // path to the log file
    protected $errorMsg;

    public function __construct($message)
    {
        // call parent constructor
        $this->errorMsg = $message;
        parent::__construct($message);
    }

}


class FinanceException extends SenshiException
{

    protected $type = "Finance Exception";
    // path to the log file
    protected $errorMsg;
    protected $log_file = 'log_finance_exceptions.txt';
    protected $flag = "CRITICAL";

    public function __construct($message, $flag = null)
    {
        if (defined('MAIL_DEBUG')) {
            $this->deubgMode = MAIL_DEBUG;
        }
        // call parent constructor
        $this->errorMsg = $message;
        if ($this->deubgMode) {
            if (defined('ENV') && ENV === 'test') {
                $message .= "\n\n *** MAIL DEBUG MODE ON ***";
            }
        }
        // Notify Palisis support (e.g. Adyen negative transaction warning)
        if (defined('ENV') && ENV === 'live') {
            // we're in production, so let's notify support helpdesk
            $this->emailNotify[] = $this->emailFinance;
            $this->emailNotify[] = $this->emailCriticalGL;
            $this->emailNotify[] = $this->emailPalisisNotifications;
        }
        $flag = $flag ? $flag : $this->flag;
        parent::__construct($message, $flag);
    }

}
