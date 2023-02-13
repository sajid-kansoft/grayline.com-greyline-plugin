<?php
namespace GrayLineTourCMSExternalFormControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AgentRegisterExternalFormBlockController 
{
    public $returnUrl = "";
    public function on_start()
    {
        $this->returnUrl = home_url('/agents/agent-register/');
    }

    // need to be check
    public function action_submit_form()
    {
        $body = "";

        $fixed_post = array();

        foreach ($_POST as $key => $value) {

            switch ($key) {

                case "email":
                    $fixed_post[$key] = filter_var($_POST[$key], FILTER_VALIDATE_EMAIL);
                    break;
                case "webaddress":
                    $fixed_post[$key] = filter_var($_POST[$key], FILTER_VALIDATE_URL);
                    break;
                case ("company" || "firstname" || "surname"):
                    $fixed_post[$key] = filter_var($_POST[$key]);
                    $fixed_post[$key] = preg_replace("(\r|\n)", "", $fixed_post[$key]);
                    break;
                default:
                    $fixed_post[$key] = filter_var($_POST[$key]);
            }

            $body .= ucwords($key).": ".$fixed_post[$key]."\n\n";

        }

        
        $subject = 'Agent signup request, '.$fixed_post['firstname'].' '.$fixed_post['surname'].' ('.$fixed_post['company'].')';

        $headers = 'From: noreply@grayline.com'. "\r\n" .
            'Reply-To: manoj.t@kansoftware.com' . "\r\n";
        $to = array('iona@palisis.com', 'agents@grayline.com');
        $sent = wp_mail($to, $subject, strip_tags($body), $headers);
            
        if($sent) {
          //message sent!       
        }
        else  {
          //message wasn't sent       
        }

        /*$this->set('email_sent', true);
        $this->set('status_message', 'Thank you for your application, we will get back to you shortly.');*/

        return true;

    }

}