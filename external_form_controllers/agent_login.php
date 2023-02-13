<?php
namespace GrayLineTourCMSExternalFormControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AgentLoginExternalFormBlockController
{
    public $controlPanelUrl;
    public $status;
    public $message;
    public $return_url;

    public function on_start()
    {
        global $wp;

        // used in JS to redirect the user to TourCMS control panel if clicked
        $this->controlPanelUrl = URL_AGENTS . '/partner/login_new.php';

        $this->return_url = home_url($wp->request);
    }


    public function action_log_out()
    {
        setcookie("tcms_ag_bk", "", strtotime("-1 hour"), "/");
        unset($_SESSION["agent_name"]);
    }

    public function action_submit_form()
    {

        $info = array();
        $info["username"] = !empty($_POST["username"]) ? $_POST["username"] : "";
        $info["password"] = !empty($_POST["password"]) ? $_POST["password"] : "";

        if ($info["username"] == "" || $info["password"] == "") {
            $this->status = 'error';
            $this->message = 'Please enter your email address and password';
        } else {

            $fields_string = "";

            $url = URL_AGENTS . '/partner/api_login_new.php';

            //url-ify the data for the POST
            //foreach($info as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
            //rtrim($fields_string, '&');
            $fields_string = http_build_query($info);

            //open connection
            $ch = curl_init();

            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url."?".$fields_string);
            //curl_setopt($ch,CURLOPT_POST, count($info));
            //curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CAINFO, "C:/wamp64/www/ca-bundle.crt");
            //execute post
            $response = curl_exec($ch);

            // Strip out result
            //$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
            //$result = substr( $response, $header_size );

            $xml = simplexml_load_string($response);

            //close connection
            curl_close($ch);

            if ($xml->error == "OK") {

                setcookie("tcms_ag_bk", htmlspecialchars($xml->agent_booking_key), strtotime("+1 day"), "/");
                $_SESSION["agent_name"] = (string)$xml->name;

                /*$this->set('status', "success");
                $this->set('message', 'Thanks, You are now logged in');
                */

                wp_redirect(home_url("/agents/agent-login"));
                exit;
            } else {

                $this->status  = 'error';
                $this->message = 'Login failed, please check your details'; 

            }
        }

        $params = "?status=".$this->status."&message=".$this->message;

        if(!isset($_POST["current_page_url"])) {

            $login_page_url = $_POST["current_page_url"];
            
            wp_redirect($login_page_url. $params);
        } else {

             wp_redirect(home_url("/agents/. $params"));
        }
        

    }

}