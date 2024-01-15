<?php

/**
 *  @package Webhook Vercel Deploy
 */
/*
Plugin Name: Webhook Vercel Deploy
Plugin URI: https://github.com/doreybenjamin/wp-webhook-vercel-deploy
Description: Adds a Build Website button that sends a webhook request to build a vercel hosted website when clicked
Version: 1.0
Author: Dorey Benjamin
Author URI: https://benjamindorey.fr/
License: GPLv3 or later
Text Domain: webhook-vercel-deploy
*/

/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

defined( 'ABSPATH' ) or die('You do not have access to this file, sorry mate');

class deployWebhook {

    /**
    * Constructor
    *
    * @since 1.0.0
    **/
    public function __construct() {

      // Stop crons on uninstall
      register_deactivation_hook(__FILE__,  array( $this, 'deactivate_scheduled_cron'));

    	// Hook into the admin menu
    	add_action( 'admin_menu', array( $this, 'create_plugin_settings_page' ) );

      // Add Settings and Fields
      add_action( 'admin_init', array( $this, 'setup_sections' ) );
      add_action( 'admin_init', array( $this, 'setup_schedule_fields' ) );
      add_action( 'admin_init', array( $this, 'setup_developer_fields' ) );
      add_action( 'admin_footer', array( $this, 'run_the_mighty_javascript' ) );
      add_action( 'admin_bar_menu', array( $this, 'add_to_admin_bar' ), 90 );

      // Listen to cron scheduler option updates
      add_action('update_option_enable_scheduled_builds', array( $this, 'build_schedule_options_updated' ), 10, 3 );
      add_action('update_option_select_schedule_builds', array( $this, 'build_schedule_options_updated' ), 10, 3 );
      add_action('update_option_select_time_build', array( $this, 'build_schedule_options_updated' ), 10, 3 );

      // Trigger cron scheduler every WP load
      add_action('wp', array( $this, 'set_build_schedule_cron') );

      // Add custom schedules
      add_filter( 'cron_schedules', array( $this, 'custom_cron_intervals' ) );

      // Link event to function
      add_action('scheduled_vercel_build', array( $this, 'fire_vercel_webhook' ) );

    }
    
    

    /**
    * Main Plugin Page markup
    *
    * @since 1.0.0
    **/
    public function plugin_settings_page_content() {?>
    	<div class="wrap">
    		<h2><?php _e('Webhook Vercel Deploy', 'webhook-vercel-deploy');?></h2>
        <hr>
        <h3><?php _e('Build Website', 'webhook-vercel-deploy');?></h3>
        <button id="build_button" class="button button-primary" name="submit" type="submit">
          <?php _e('Build Site', 'webhook-vercel-deploy');?>
        </button>
        <br>
        <p id="build_status" style="font-size: 12px; margin: 0;"></p>
        <p style="font-size: 12px">*<?php _e('Do not abuse the Build Site button', 'webhook-vercel-deploy');?>*</p><br>
        <hr>
        <h3><?php _e('Deploy Status', 'webhook-vercel-deploy');?></h3>
        <button id="status_button" class="button button-primary" name="submit" type="submit" style="margin: 0 0 16px;">
          <?php _e('Last Deploys Status', 'webhook-vercel-deploy');?>
        </button>
        <div class="content-status" style="display:none">
            <div style="margin: 0 0 16px;">
                <a id="build_img_link" href="">
                    <img id="build_img" src=""/>
                </a>
            </div>
            <!-- <p id="deploy_status"></p> -->
            <p id="deploy_id"></p>
            <div><p id="deploy_finish_time" ></p><p id="deploy_finish_status"></p><p id="deploy_loading"></p></div>
        </div>

        <div id="deploy_preview"></div>

        <hr>

        <h3><?php _e('Previous Builds', 'webhook-vercel-deploy');?></h3>
        <button id="previous_deploys" class="button button-primary" name="submit" type="submit" style="margin: 0 0 16px;">
          <?php _e('Get All Previous Deploys', 'webhook-vercel-deploy');?>
        </button>
        <ul id="previous_deploys_container" style="list-style: none;"></ul>
    	</div> <?php
    }

    /**
    * Schedule Builds (subpage) markup
    *
    * @since 1.1.2
    **/
    public function plugin_settings_schedule_content() {?>
    	<div class="wrap">
    		<h1><?php _e('Schedule Vercel Builds', 'webhook-vercel-deploy');?></h1>
    		<p><?php _e('This section allows regular Vercel builds to be scheduled.', 'webhook-vercel-deploy');?></p>
        <hr>

        <?php
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ){
              $this->admin_notice();
        } ?>

        <form method="POST" action="options.php">
                <?php
                    settings_fields( 'schedule_webhook_fields' );
                    do_settings_sections( 'schedule_webhook_fields' );
                    submit_button();
                ?>
        </form>
    	</div> <?php
    }

    /**
    * Developer Settings (subpage) markup
    *
    * @since 1.0.0
    **/
    public function plugin_settings_developer_content() {?>
    	<div class="wrap">
    		<h1><?php _e('Developer Settings', 'webhook-vercel-deploy');?></h1>
    		<p><?php _e('Do not change this if you dont know what you are doing.', 'webhook-vercel-deploy');?></p>
            <hr>

            <?php
            if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ){
                  $this->admin_notice();
            } ?>
    		<form method="POST" action="options.php">
                <?php
                    settings_fields( 'developer_webhook_fields' );
                    do_settings_sections( 'developer_webhook_fields' );
                    submit_button();
                ?>
    		</form>

            <footer>
                <h3><?php _e('Extra Info', 'webhook-vercel-deploy');?></h3>
                <p><a href="https://vercel.com/docs/deployments/deploy-hooks"><?php _e('Creating a Deploy Hook', 'webhook-vercel-deploy');?></a></p>
                <p><b>Where to find Vercel Site ID</b></p>
                <p>You can find the org and project IDs in the project.json file in the .vercel folder of your local project.</p>
                <p><a href="https://vercel.com/docs/rest-api#authentication" target="_blank"><?php _e('Vercel API Key', 'webhook-vercel-deploy');?></a></p>
            </footer>

    	</div> <?php
    }

    /**
    * The Mighty JavaScript
    *
    * @since 1.0.0
    **/
    public function run_the_mighty_javascript() {
        // TODO: split up javascript to allow to be dynamically imported as needed
        // $screen = get_current_screen();
        // if ( $screen && $screen->parent_base != 'developer_webhook_fields' && $screen->parent_base != 'deploy_webhook_fields_sub' ) {
        //     return;
        // }
        ?>
        <script type="text/javascript" >
        jQuery(document).ready(function($) {
            var _this = this;
            $( ".webhook-deploy_page_developer_webhook_fields td > input" ).css( "width", "100%");

            var webhook_url = '<?php echo(get_option('webhook_address')) ?>';
            var vercel_user_agent = '<?php echo(get_option('vercel_user_agent')) ?>';
            var vercel_api_key  = '<?php echo(get_option('vercel_api_key'))?>'
            var vercel_site_id = '<?php echo(get_option('vercel_site_id')) ?>';

            var vercelSites = "https://api.vercel.com/v6/";
            var req_url = vercelSites + 'deployments?projectId=' + vercel_site_id;

            function getDeployData() {
                $.ajax({
                    type: "GET",
                    url: req_url,
                    headers: {
                        Authorization: 'Bearer ' + vercel_api_key,
                    }
                }).done(function(data) {
                    appendStatusData(data.deployments[0]);
                })
                .fail(function() {
                    console.error("error res => ", this)
                })
            }
                        
            function getAllPreviousBuilds() {
                $.ajax({
                    type: "GET",
                    url: req_url,
                    headers: {
                        Authorization: 'Bearer ' + vercel_api_key,
                    }
                }).done(function(data) {
                    data.deployments.forEach(function(item) {
                        var deploy_preview_url = '';
                        if (data.url) {
                            deploy_preview_url = data.url
                        } else {
                            deploy_preview_url = data.url
                        }
                        var createdAt = new Date(item.createdAt);
                        var formattedCreatedAt = createdAt.toLocaleString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric' });
                        var buildingAt = new Date(item.buildingAt);
                        var formattedbuildingAt = buildingAt.toLocaleString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric' });
                        $('#previous_deploys_container').append(
                            '<li style="margin: 0 auto 16px"><hr><h3>' + item.name + '</h3><h4>Created at: ' +  formattedCreatedAt + '</h4><p>Deploy Time: ' + formattedbuildingAt + '</p><p>Branch: ' + item.meta.githubCommitRef + '</p><p>State: ' + item.state + '</p><img id="build_img" src="' + getBuildImgSrc(item.state) + '"></li>'
                        );
                        
                        function getBuildImgSrc(state) {
                            if (state === 'CANCELED') {
                                return '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-none.svg"; ?>';
                            }
                            if (state === 'ERROR') {
                                return '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-failed.svg"; ?>';
                            }
                            if (state === 'INITIALIZING' || state === 'QUEUED') {
                                return '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-pending.svg"; ?>';
                            }
                            if (state === 'READY') {
                                return '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-ready.svg"; ?>';
                            }
                        }
                    })
                })
                .fail(function() {
                    console.error("error res => ", this)
                })
            }

            function runSecondFunc() {
                $.ajax({
                    type: "GET",
                    url: req_url,
                    headers: {
                        Authorization: 'Bearer ' + vercel_api_key,
                    }
                }).done(function(data) {
                    $( "#build_img_link" ).attr("href", `${data.admin_url}`);
                    // $( "#build_img" ).attr("src", `https://api.vercel.com/api/v1/badges/${ vercel_site_id }/deploy-status`);
                })
                .fail(function() {
                    console.error("error res => ", this)
                })

                // $( "#build_status" ).html('Deploy building');
            }

            function appendStatusData(data) {
                var d = new Date();
                var p = d.toLocaleString();
                var yo = new Date(data.createdAt);
                var created = yo.toLocaleString();
                var current_state = data.state;
                console.log(data.state)
                if (data.state === 'READY') {
                    $( "#wp-admin-bar-vercel-deploy-button" ).css('opacity', '1');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).removeClass('running').css('opacity', '1');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).removeClass('deploying');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).find('.ab-label').text('Deploy Site');
                    $( "#build_img" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-ready.svg"; ?>');
                    $( "#admin-bar-vercel-deploy-status-badge" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-ready.svg"; ?>');
                }

                if (data.state === 'BUILDING') {
                    $( "#build_img" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-building.svg"; ?>');
                    //Top admin css
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).addClass('running').css('opacity', '0.5');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).addClass('deploying');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).find('.ab-label').text('Deploying…');
                    $( "#admin-bar-vercel-deploy-status-badge" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-building.svg"; ?>');
                } else {
                    if (data.state === 'CANCELED') {
                    $( "#build_img" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-none.svg"; ?>');
                    //Top admin css
                    $( "#wp-admin-bar-vercel-deploy-button" ).css('opacity', '1');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).removeClass('running').css('opacity', '1');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).removeClass('deploying');
                    $( "#wp-admin-bar-vercel-deploy-button .ab-empty-item" ).find('.ab-label').text('Deploy Site');
                    $( "#admin-bar-vercel-deploy-status-badge" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-none.svg"; ?>');
                    }
                    if (data.state === 'ERROR') {
                    $( "#build_img" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-failed.svg"; ?>');
                    $( "#admin-bar-vercel-deploy-status-badge" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-failed.svg"; ?>');
                    }
                    if (data.state === 'INITIALIZING') {
                    $( "#build_img" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-pending.svg"; ?>');
                    $( "#admin-bar-vercel-deploy-status-badge" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-failed.svg"; ?>');
                    }
                    if (data.state === 'QUEUED') {
                    $( "#build_img" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-pending.svg"; ?>');
                    $( "#admin-bar-vercel-deploy-status-badge" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-failed.svg"; ?>');
                    }
                    $( "#deploy_finish_time" ).html( "Build Completed: " + created );
                    $( "#deploy_finish_status" ).html( "Build Status: " + data.state );
                }


            }
            
            getDeployData()

            function vercelDeploy() {
                return $.ajax({
                    type: "POST",
                    url: webhook_url,
                    dataType: "json"
                });
            }

            $("#status_button").on("click", function(e) {
                e.preventDefault();
                getDeployData();
                $('.content-status').css('display', 'block');
            });

            $("#previous_deploys").on("click", function(e) {
                e.preventDefault();
                getAllPreviousBuilds();
            });

            $("#build_button").on("click", function(e) {

                // hide deploy
                $('#build_img_link').attr('href', '');
                $('#build_img').attr('src', '');
                $('#deploy_id').html('');
                $('#deploy_finish_time').html('');
                $('#deploy_finish_status').html('');
                $('#deploy_preview').html('');
                $('.content-status').css('display', 'block');

                e.preventDefault();
                
                var interval = setInterval(function() {
                    getDeployData();
                }, 2000);
                
                // Stop checking after 5 minutes (300,000 milliseconds)
                setTimeout(function() {
                    clearInterval(interval);
                }, 300000);
                
                vercelDeploy().done(function() {
                    getDeployData();
                    $( "#build_status" ).html('Deploy building');
                    $( "#build_status" ).css('margin-top', '10px');
                })
                .fail(function() {
                    console.error("error res => ", this)
                    $( "#build_status" ).html('There seems to be an error with the build', this);
                })
            });

            $(document).on('click', '#wp-admin-bar-vercel-deploy-button', function(e) {
                e.preventDefault();

                var $button = $(this),
                $buttonContent = $button.find('.ab-item:first');
                    
                $( "#admin-bar-vercel-deploy-status-badge" ).attr("src", '<?php echo plugin_dir_url( __FILE__ ) . "assets/vercel-building.svg"; ?>');

                if ($button.hasClass('deploying') || $button.hasClass('running')) {
                    return false;
                }
                
                var interval = setInterval(function() {
                    getDeployData();
                }, 2000);
                
            
                // Stop checking after 5 minutes (300,000 milliseconds)
                setTimeout(function() {
                    clearInterval(interval);
                }, 300000);
                

                $button.addClass('running').css('opacity', '0.5');

                vercelDeploy().done(function() {
                    var $badge = $('#admin-bar-vercel-deploy-status-badge');

                    $button.removeClass('running');
                    $button.addClass('deploying');

                    $buttonContent.find('.ab-label').text('Deploying…');
                })
                .fail(function() {
                    $button.removeClass('running').css('opacity', '1');
                    $buttonContent.find('.dashicons-hammer')
                        .removeClass('dashicons-hammer').addClass('dashicons-warning');

                    console.error("error res => ", this)
                })
            });
            
        });
        </script> <?php
    }

    /**
    * Plugin Menu Items Setup
    *
    * @since 1.0.0
    **/
    public function create_plugin_settings_page() {
        $run_deploys = apply_filters( 'vercel_deploy_capability', 'manage_options' );
        $adjust_settings = apply_filters( 'vercel_adjust_settings_capability', 'manage_options' );

        if ( current_user_can( $run_deploys ) ) {
            $page_title = __('Deploy to Vercel', 'webhook-vercel-deploy');
            $menu_title = __('Deployment', 'webhook-vercel-deploy');
            $capability = $run_deploys;
            $slug = 'deploy_webhook_fields';
            $callback = array( $this, 'plugin_settings_page_content' );
            $icon = 'dashicons-admin-plugins';
            $position = 100;

            add_menu_page( $page_title, $menu_title, $capability, $slug, $callback, $icon, $position );
        }

        if ( current_user_can( $adjust_settings ) ) {
            $sub_page_title = __('Schedule Builds', 'webhook-vercel-deploy');
            $sub_menu_title = __('Schedule Builds', 'webhook-vercel-deploy');
            $sub_capability = $adjust_settings;
            $sub_slug = 'schedule_webhook_fields';
            $sub_callback = array( $this, 'plugin_settings_schedule_content' );

            add_submenu_page( $slug, $sub_page_title, $sub_menu_title, $sub_capability, $sub_slug, $sub_callback );
        }

        if ( current_user_can( $adjust_settings ) ) {
            $sub_page_title = __('Developer Settings', 'webhook-vercel-deploy');
            $sub_menu_title = __('Developer Settings', 'webhook-vercel-deploy');
            $sub_capability = $adjust_settings;
            $sub_slug = 'developer_webhook_fields';
            $sub_callback = array( $this, 'plugin_settings_developer_content' );

            add_submenu_page( $slug, $sub_page_title, $sub_menu_title, $sub_capability, $sub_slug, $sub_callback );
        }


    }

    public function custom_cron_intervals($schedules) {
      // add a 'weekly' interval
      $schedules['weekly'] = array(
        'interval' => 604800,
        'display' => __('Once Weekly', 'webhook-vercel-deploy')
      );
      $schedules['monthly'] = array(
        'interval' => 2635200,
        'display' => __('Once a month', 'webhook-vercel-deploy')
      );

      return $schedules;
    }

    /**
    * Notify Admin on Successful Update
    *
    * @since 1.0.0
    **/
    public function admin_notice() { ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Your settings have been updated!', 'webhook-vercel-deploy');?></p>
        </div>
    <?php
    }

    /**
    * Setup Sections
    *
    * @since 1.0.0
    **/
    public function setup_sections() {
        add_settings_section( 'schedule_section', __('Scheduling Settings', 'webhook-vercel-deploy'), array( $this, 'section_callback' ), 'schedule_webhook_fields' );
        add_settings_section( 'developer_section', __('Webhook Settings', 'webhook-vercel-deploy'), array( $this, 'section_callback' ), 'developer_webhook_fields' );
    }

    /**
    * Check it wont break on build and deploy
    *
    * @since 1.0.0
    **/
    public function section_callback( $arguments ) {
    	switch( $arguments['id'] ){
    		case 'developer_section':
    			echo __('The build and deploy status will not work without these fields entered corrently', 'webhook-vercel-deploy');
    			break;
    	}
    }

    /**
    * Fields used for schedule input data
    *
    * Based off this article:
    * @link https://www.smashingmagazine.com/2016/04/three-approaches-to-adding-configurable-fields-to-your-plugin/
    *
    * @since 1.1.0
    **/
    public function setup_schedule_fields() {
        $fields = array(
          array(
            'uid' => 'enable_scheduled_builds',
            'label' => __('Enable Scheduled Events', 'webhook-vercel-deploy'),
            'section' => 'schedule_section',
            'type' => 'checkbox',
            'options' => array(
              'enable' => __('Enable', 'webhook-vercel-deploy'),
              ),
            'default' =>  array()
          ),
          array(
            'uid' => 'select_time_build',
            'label' => __('Select Time to Build', 'webhook-vercel-deploy'),
            'section' => 'schedule_section',
            'type' => 'time',
            'default' => '00:00'
          ),
          array(
            'uid' => 'select_schedule_builds',
            'label' => __('Select Build Schedule', 'webhook-vercel-deploy'),
            'section' => 'schedule_section',
            'type' => 'select',
            'options' => array(
              'daily' => __('Daily', 'webhook-vercel-deploy'),
              'weekly' => __('Weekly', 'webhook-vercel-deploy'),
              'monthly' => __('Monthly', 'webhook-vercel-deploy'),
            ),
            'default' => array('week')
          )
        );
    	foreach( $fields as $field ){
        	add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), 'schedule_webhook_fields', $field['section'], $field );
            register_setting( 'schedule_webhook_fields', $field['uid'] );
    	}
    }

    /**
    * Fields used for developer input data
    *
    * @since 1.0.0
    **/
    public function setup_developer_fields() {
        $fields = array(
          array(
            'uid' => 'webhook_address',
            'label' => __('Webhook Build URL', 'webhook-vercel-deploy'),
            'section' => 'developer_section',
            'type' => 'text',
                'placeholder' => 'https://',
                'default' => '',
            ),
            array(
            'uid' => 'vercel_site_id',
            'label' => __('Vercel Site ID', 'webhook-vercel-deploy'),
            'section' => 'developer_section',
            'type' => 'text',
                'placeholder' => 'e.g. 5b8e927e-82e1-4786-4770-a9a8321yes43',
                'default' => '',
            ),
            array(
            'uid' => 'vercel_api_key',
            'label' => __('Vercel API Key', 'webhook-vercel-deploy'),
            'section' => 'developer_section',
            'type' => 'text',
                'placeholder' => 'e.g. 5b8e927e-82e1-4786-4770-a9a8321yes43',
                'default' => '',
          ),
        );
      foreach( $fields as $field ){
          add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), 'developer_webhook_fields', $field['section'], $field );
            register_setting( 'developer_webhook_fields', $field['uid'] );
      }
    }

    /**
    * Field callback for handling multiple field types
    *
    * @since 1.0.0
    * @param $arguments
    **/
    public function field_callback( $arguments ) {

        $value = get_option( $arguments['uid'] );

        if ( !$value ) {
            $value = $arguments['default'];
        }

        switch( $arguments['type'] ){
            case 'text':
            case 'password':
            case 'number':
                printf( '<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], $value );
                break;
            case 'time':
              printf( '<input name="%1$s" id="%1$s" type="time" value="%2$s" />', $arguments['uid'], $value );
              break;
            case 'textarea':
                printf( '<textarea name="%1$s" id="%1$s" placeholder="%2$s" rows="5" cols="50">%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], $value );
                break;
            case 'select':
            case 'multiselect':
                if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
                    $attributes = '';
                    $options_markup = '';
                    foreach( $arguments['options'] as $key => $label ){
                        $options_markup .= sprintf( '<option value="%s" %s>%s</option>', $key, selected( $value[ array_search( $key, $value, true ) ], $key, false ), $label );
                    }
                    if( $arguments['type'] === 'multiselect' ){
                        $attributes = ' multiple="multiple" ';
                    }
                    printf( '<select name="%1$s[]" id="%1$s" %2$s>%3$s</select>', $arguments['uid'], $attributes, $options_markup );
                }
                break;
            case 'radio':
            case 'checkbox':
                if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
                    $options_markup = '';
                    $iterator = 0;
                    foreach( $arguments['options'] as $key => $label ){
                        $iterator++;
                        $options_markup .= sprintf( '<label for="%1$s_%6$s"><input id="%1$s_%6$s" name="%1$s[]" type="%2$s" value="%3$s" %4$s /> %5$s</label><br/>', $arguments['uid'], $arguments['type'], $key, checked( count($value) > 0 ? $value[ array_search( $key, $value, true ) ] : false, $key, false ), $label, $iterator );
                    }
                    printf( '<fieldset>%s</fieldset>', $options_markup );
                }
                break;
        }
    }

    /**
    * Add Deploy Button and Deployment Status to admin bar
    *
    * @since 1.1.0
    **/
    public function add_to_admin_bar( $admin_bar ) {

        $see_deploy_status = apply_filters( 'vercel_status_capability', 'manage_options' );
        $run_deploys = apply_filters( 'vercel_deploy_capability', 'manage_options' );

        if ( current_user_can( $run_deploys ) ) {
            $webhook_address = get_option( 'webhook_address' );

            if ( $webhook_address ) {
                $button = array(
                    'id' => 'vercel-deploy-button',
                    'title' => '<div style="cursor: pointer;"><span class="ab-icon dashicons dashicons-hammer"></span> <span class="ab-label">'. __('Deploy Site', 'webhook-vercel-deploy') .'</span></div>'
                );

                $admin_bar->add_node( $button );
            }
        }

        if ( current_user_can( $see_deploy_status ) ) {
                $state;
                $vercel_site_id = get_option( 'vercel_site_id' );
        
                if ( $vercel_site_id ) {
                    $badge = array(
                        'id' => 'vercel-deploy-status-badge',
                        'title' => sprintf( '<div style="display: flex; height: 100%%; align-items: center;">
                                <img id="admin-bar-vercel-deploy-status-badge" src="'. plugin_dir_url( __FILE__ ) . '"assets/vercel-pending.svg" alt="'. __('Vercel deploy status', 'webhook-vercel-deploy') .'" style="width: auto; height: 16px;" />
                            </div>' )
                    );
        
                    $admin_bar->add_node( $badge );
                }
        }

    }

    /**
    *
    * Manage the cron jobs for triggering builds
    *
    * Check if scheduled builds have been enabled, and pass to
    * the enable function. Or disable.
    *
    * @since 1.1.2
    **/
    public function build_schedule_options_updated() {
      $enable_builds = get_option( 'enable_scheduled_builds' );
      if( $enable_builds ){
        // Clean any previous setting
        $this->deactivate_scheduled_cron();
        // Reset schedule
        $this->set_build_schedule_cron();
      } else {
        $this->deactivate_scheduled_cron();
      }
    }

    /**
    *
    * Activate cron job to trigger build
    *
    * @since 1.1.2
    **/
    public function set_build_schedule_cron() {
      $enable_builds = get_option( 'enable_scheduled_builds' );
      if( $enable_builds ){
        if( !wp_next_scheduled('scheduled_vercel_build') ) {
          $schedule = get_option( 'select_schedule_builds' );
          $set_time = get_option( 'select_time_build' );
          $timestamp = strtotime( $set_time );
          wp_schedule_event( $timestamp , $schedule[0], 'scheduled_vercel_build' );
        }
      } else {
        $this->deactivate_scheduled_cron();
      }
    }

    /**
    *
    * Remove cron jobs set by this plugin
    *
    * @since 1.1.2
    **/
    public function deactivate_scheduled_cron(){
      // find out when the last event was scheduled
    	$timestamp = wp_next_scheduled('scheduled_vercel_build');
    	// unschedule previous event if any
    	wp_unschedule_event($timestamp, 'scheduled_vercel_build');
    }

    /**
    *
    * Trigger Vercel Build
    *
    * @since 1.1.2
    **/
    public function fire_vercel_webhook(){
      $vercel_user_agent = get_option('vercel_user_agent');
      $webhook_url = get_option('webhook_address');
      if($vercel_user_agent && $webhook_url){
        $options = array(
          'method'  => 'POST',
        );
        return wp_remote_post($webhook_url, $options);
      }
      return false;
    }

}

new deployWebhook;

function get_deploy_data_callback() {
    // Retrieve the necessary data
    $deployData = $_POST['deployData'];

    // Process the data and prepare the response
    $response = 'Data received: ' . $deployData;

    // Return the response
    echo $response;

    // Always exit after handling AJAX requests
    wp_die();
}
add_action( 'wp_ajax_get_deploy_data', 'get_deploy_data_callback' );
add_action( 'wp_ajax_nopriv_get_deploy_data', 'get_deploy_data_callback' );
?>
