<?php
namespace GrayLineTourCMSExternalFormControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AffiliateRegisterExternalFormBlockController
{
    public $returnUrl;

    public function on_start()
    {
        $this->returnUrl = home_url("/affiliates/affiliate-register/");
    }

    public function action_submit_form()
    {
        $mh = Loader::helper('mail');

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

        $mh->setSubject(
            'Affiliate signup request, '.$fixed_post['firstname'].' '.$fixed_post['surname'].' ('.$fixed_post['company'].')'
        );
        $mh->setBody($body);
        $mh->to('iona@palisis.com', 'Iona');
        $mh->to('agents@grayline.com', 'Gray Line Agents');
        $mh->from('noreply@grayline.com');
        // Doesn't appear to set a return value so can't check it
        //$response = $mh->sendMail();
        $mh->sendMail();

        $this->set('email_sent', true);
        $this->set('status_message', 'Thank you for your application, we will get back to you shortly.');

        return true;

    }

}
