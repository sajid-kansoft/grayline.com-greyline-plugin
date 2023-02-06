<?php

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class Filter_Rest_Api_Endpoints {

    private $my_routes;

    public function __construct( $routes ) {
        $this->my_routes = $routes;
        add_filter( 'rest_endpoints', array( $this, 'run_filter' ) );
    }

    public function run_filter( $endpoints ) {

        foreach ( $endpoints as $route => $endpoint ) {
            $keep_route = false;

            foreach ( $this->my_routes as $my_route ) {

                if ( strpos( $route, $my_route ) !== false ) {
                    $keep_route = true;
                    break;
                }
            }

            if ( !$keep_route ) {
                unset( $endpoints[ $route ] );
            }
        }

        return $endpoints;
    }
}