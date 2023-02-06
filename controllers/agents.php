<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use SimpleXMLElement;
use \AgentException as AgentException; 

class AgentsController extends MainController
{
    
    protected $description = 'Agent Login';
    protected $final_url;   // Your destination_url here (step2.php in this sample project)
    protected $cancel_url;  // Your cancel page url here (cancel_page.html in this sample project)
    protected $self_url_abs;
    protected $logged_in = false;

    public $agentName;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->isEnabled = false;
        $this->action = '';

        $this->gen_urls();
    }

    public function on_start()
    {
        $this->logout_agent();
    }

    public function view()
    {
        $this->enabled();
        $this->process_action();
    }

    public function form_submit_controller() {
        try {
            // \FB::info("POST vars");
            // \FB::info($_POST);

            $this->login_agent();
            /*
            $json = null;
            $success =false;
            $data = array("error" => "testing");
            $json = json_encode($data);
            \FB::warn($json);
            if (!$success)
            {
                throw new AgentException($data);
            }
            */
        }
        catch( AgentException $e ) {
            // \FB::error("catch exception invoice payment exception");
            // This can stop JSON responses from working, so let's just log the error instead.
            // echo "<div class='message error'>We seem to be having issues taking payment. We are sorry for the inconvenience. </div>";
        }
        catch ( Exception $e ) {
            //echo "<div class='message error'>" . $e->getMessage() . "</div>";
        }
    }

    protected function login_agent()
    {
        // $this->genUrls();
        $chID = get_option('grayline_tourcms_wp_channel');

        $params = new SimpleXMLElement("<params />");
        $params->final_url = $this->final_url;
        $params->cancel_page_url = $this->cancel_url;

        $tc = self::getTc();
        $result = $tc->start_new_agent_login($params, $chID);

        // FB::log("XML passed to TourCMS: ");
        // FB::log($params);

        // Store response needed data in session.
        $_SESSION["final_url"] = (string)$result->final_url;
        $_SESSION["login_page_url"] = (string)$result->login_page_url;
        $_SESSION["public_token"] = (string)$result->public_token;
        $_SESSION["private_token"] = (string)$result->private_token;

        $ret = [];
        $ret['login_page_url'] = (string)$result->login_page_url;
        
        echo json_encode($ret); 
        exit;
    }

    protected function process_action()
    {
        if(!empty($_GET['action'])) $this->action = $_GET['action'];
        if ($this->action == 'cancel') {
            $_SESSION["public_token"] = null;
            $_SESSION["private_token"] = null;
        }
        else if ($this->action == 'final') {
            $private_token = $_SESSION["private_token"];
            
            // Call retrieve_agent_booking_key
            $tc = self::getTc();

            $channel_id = get_option('grayline_tourcms_wp_channel');

            try {
                $result = $tc->retrieve_agent_booking_key($private_token, $channel_id);
                if ($result->error != 'OK') {
                    $data = json_encode($result, JSON_UNESCAPED_SLASHES);
                    throw new AgentException($data);
                }
                else {
                    $this->loggedIn = true;
                    $token = $result->agent_booking_key;
                    $expire = $result->booking_key_valid_for;
                    $this->agentName = $result->name;
                    $this->agent_cookie($token, $expire, $this->agentName);
                }
            } catch( AgentException $e ) {
                \FB::error("catch exception invoice payment exception");
                // This can stop JSON responses from working, so let's just log the error instead.
                //echo "<div class='message error'>We seem to be having issues taking payment. We are sorry for the inconvenience. </div>";
            }
            catch ( Exception $e ) {
                //echo "<div class='message error'>" . $e->getMessage() . "</div>";
            }
        }
    }

    protected function logout_agent()
    {
        if (!empty($_GET['logout'])) {
            if (isset($_COOKIE['agent_bKEY'])) {
                unset($_COOKIE['agent_bKEY']);
                unset($_COOKIE['agentName']);
                setcookie('agent_bKEY', null, -1, '/');
                setcookie('agentName', null, -1, '/');
                wp_redirect('./');
                return true;
            } else {
                return false;
            }
        }
    }

    protected function agent_cookie($token, $expire, $agentName)
    {
        $expire = time() + $expire;
        setcookie("agent_bKEY", $token, $expire, '/');
        setcookie("agentName", $agentName, $expire, '/');
        // FB::log( "cookie is " . $_COOKIE["agent_bKEY"] );
        // FB::log( "agent name is " . $_COOKIE["agentName"] );
        // FB::log("expire is $expire");
    }

    protected function gen_urls()
    {
        global $wp;
        $this->self_url = home_url('agents/');
        $this->self_url_abs = $this->self_url;
        $this->final_url = $this->self_url . "?action=final";
        $this->cancel_url = $this->self_url . "?action=cancel";;
    }

    protected function enabled()
    {
        $this->isEnabled = true;
        $this->loggedIn = $this->get_agent() ? $this->get_agent() : false;
        /*
        if (defined('AGENTS') && AGENTS)
        {
            $this->isEnabled = true;
        }
        */
    }

    protected function get_agent()
    {
        $agentName = isset($_COOKIE['agentName']) ? $_COOKIE['agentName'] : null;
        $this->agentName = $agentName;
        return $agentName;
    }
}