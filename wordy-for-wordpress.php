<?php

/*
  Plugin Name: Wordy for WordPress
  Plugin URI: https://wordy.com/wordpress-proofreading-service/
  Description: Real-time, human, copy-editing and proofreading for everything you write.
  Version: 0.1
  Author: Wordy
  Author URI: http://wordy.com

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class Wordy_For_WordPress {

    private $wfw_notice = '';

    function __construct() {

        define( 'WFW_WP_VERSION', '3.3.1' );
        define( 'WFW_VERSION', '0.1' );
        define( 'WORDY_URL', 'http://wordy.com' );
        define( 'WFW_USER_AGENT', 'Wordy_WordPress/' . WFW_VERSION . ' (+https://wordy.com/wordpress-proofreading-service/)' );

        add_action( 'plugins_loaded', array( &$this, 'load_languages' ) );
        add_action( 'admin_menu', array( &$this, 'add_menu_items' ) );
        add_action( 'admin_init', array( &$this, 'register_settings' ) );
        add_action( 'admin_init', array( &$this, 'system_check' ), 0 );
        add_action( 'admin_print_styles', array( &$this, 'admin_styles' ) );
        add_action( 'admin_print_scripts', array( &$this, 'admin_scripts' ) );
        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
        add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widget' ) );
        add_action( 'save_post', array( &$this, 'send_to_wordy' ) );
        add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );
        add_action( 'wp_ajax_get_conversation', array( &$this, 'get_conversation_callback' ) );
        add_action( 'wp_ajax_update_conversation', array( &$this, 'update_conversation_callback' ) );
        add_action( 'wp_ajax_wordy_api', array( &$this, 'wordy_api_callback' ) );
        add_action( 'manage_posts_custom_column', array( &$this, 'custom_column' ), 10, 2 );
        add_action( 'manage_pages_custom_column', array( &$this, 'custom_column' ), 10, 2 );
        add_filter( 'manage_posts_columns', array( &$this, 'column_headings' ) );
        add_filter( 'manage_pages_columns', array( &$this, 'column_headings' ) );
        add_filter( 'get_sample_permalink_html', array( &$this, 'set_sample_permalink_html' ), '', 4 );
        //add_filter( 'post_row_actions', array( &$this, 'post_row_actions' ), 10, 2 );

        $options = get_option( 'wfw_options' );
        $this->wfw_authorized = (isset( $options['authorized'] ) && $options['authorized'] ? true : false );
        $this->timezone = (get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : date_default_timezone_get() );
    }

    function load_languages() {
        load_plugin_textdomain( 'wordy-for-wordpress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    function reset( $post_id ) {
        delete_post_meta( $post_id, 'wordy_id' );
        delete_post_meta( $post_id, 'wfw_revision_id' );
    }

    function register_settings() {
        add_settings_section( 'account_section', __( 'Account', 'wordy-for-wordpress' ), array( &$this, 'section_text' ), 'wfw_options_page' );
        register_setting( 'wfw_options_group', 'wfw_options', array( &$this, 'sanitize_callback' ) );
        add_settings_field( 'api_username', __( 'Username', 'wordy-for-wordpress' ), array( &$this, 'set_api_username' ), 'wfw_options_page', 'account_section' );
        add_settings_field( 'api_key', __( 'API Key', 'wordy-for-wordpress' ), array( &$this, 'set_api_key' ), 'wfw_options_page', 'account_section' );
        if ( $this->wfw_authorized ) {
            add_settings_section( 'options_section', __( 'Options', 'wordy-for-wordpress' ), array( &$this, 'section_text' ), 'wfw_options_page' );
            add_settings_field( 'content_rewrite', __( 'Content rewrite', 'wordy-for-wordpress' ), array( &$this, 'set_content_rewrite' ), 'wfw_options_page', 'options_section' );
            add_settings_field( 'language_id', __( 'Language', 'wordy-for-wordpress' ), array( &$this, 'set_language_id' ), 'wfw_options_page', 'options_section' );
        }
    }

    function system_check() {
        global $wp_version;
        $plugin = plugin_basename( __FILE__ );
        if ( is_plugin_active( $plugin ) ) {
            if ( version_compare( $wp_version, WFW_WP_VERSION, '<' ) ) {
                $message = sprintf( __( 'Wordy for WordPress requires WordPress %s or higher.', 'wordy-for-wordpress' ), WFW_WP_VERSION );
            } elseif ( !function_exists( 'curl_init' ) ) {
                $message = __( 'Wordy for WordPress requires cURL to be enabled.', 'wordy-for-wordpress' );
            } elseif ( !WP_POST_REVISIONS ) {
                $message = __( 'Wordy for WordPress requires WordPress revisions to be enabled.', 'wordy-for-wordpress' );
            }
            if ( isset( $message ) ) {
                deactivate_plugins( $plugin );
                $message .= '<p>' . $message . ' ' . __( 'Deactivating Plugin.', 'wordy-for-wordpress' ) . '</p>';
                $message .= '<p><a href="' . admin_url() . '">' . __( 'Back to WordPress Admin', 'wordy-for-wordpress' ) . '</a></p>';
                wp_die( $message );
            }
        }
    }

    function set_api_username() {
        $options = get_option( 'wfw_options' );
        echo '<input type="text" name="wfw_options[api_username]" id="api_username" class="regular-text" value="' . (isset( $options['api_username'] ) ? $options['api_username'] : '') . '" /> <span class="description">' . __( 'The email address you use to log in to Wordy' ) . '</span>';
    }

    function set_api_key() {
        $options = get_option( 'wfw_options' );
        echo '<input type="text" name="wfw_options[api_key]" id="api_key" class="regular-text" value="' . (isset( $options['api_key'] ) ? $options['api_key'] : '') . '" /> <span class="description">' . sprintf( __( 'API key generated %1$sfrom your account', 'wordy-for-wordpress' ), '<a href="' . WORDY_URL . '/account/apikeys/" target="_blank">' ) . '</a></span>';
    }

    function set_content_rewrite() {
        $options = get_option( 'wfw_options' );
        echo '<input type="hidden" name="wfw_options[content_rewrite]" value="0" />';
        echo '<input type="checkbox" name="wfw_options[content_rewrite]" id="content_rewrite" value="1" ' . checked( isset( $options['content_rewrite'] ) && $options['content_rewrite'], 1, false ) . ' /> <span class="description">' . __( 'Substantially reword content to improve consistency, flow and the natural use of language', 'wordy-for-wordpress' ) . '</span>';
    }

    function set_language_id() {
        $options = get_option( 'wfw_options' );
        echo '<p><select name="wfw_options[language_id]" id="default_language">';
        foreach ( $options['languages'] as $key => $language ) {
            echo '<option value="' . $key . '" ' . selected( $options['language_id'], $key, false ) . '>' . $language . '</option>';
        }
        echo '</select> <span class="description">' . __( 'The language of your content', 'wordy-for-wordpress' ) . '</span>';
        echo '</p>';
    }

    function admin_notices() {
        global $post;
        global $pagenow;
        if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && in_array( $post->post_type, array( 'post', 'page' ) ) ) {
            if ( $this->wfw_notice ) {
                echo $this->wfw_notice;
            }
        }
        if ( !$this->wfw_authorized ) {
            echo '<div class="error wfw-notice"><p>' . sprintf( __( 'Please enter your Wordy API email and key %1$shere%2$s', 'wordy-for-wordpress' ), '<a href="' . get_admin_url() . 'options-general.php?page=wfw_options_page">', '</a>' ) . '</p></div>';
        }
    }

    function add_menu_items() {
        $this->wfw_options_hook = add_options_page( __( 'Wordy for WordPress options', 'wordy-for-wordpress' ), __( 'Wordy for WordPress', 'wordy-for-wordpress' ), 'manage_options', 'wfw_options_page', array( &$this, 'wfw_options_page_callback' ) );
        add_action( 'load-' . $this->wfw_options_hook, array( &$this, 'add_help_tab' ) );
        if ( $this->wfw_authorized ) {
            add_action( 'load-post.php', array( &$this, 'add_help_tab' ) );
            add_action( 'load-post-new.php', array( &$this, 'add_help_tab' ) );
        }
    }

    function add_help_tab() {
        $screen = get_current_screen();
        if ( method_exists( $screen, 'add_help_tab' ) ) {
            if ( in_array( $screen->post_type, array( 'post', 'page' ) ) ) {

                $wordy_help = '<p>' . __( ' Wordy for WordPress lets you seamlessly send your blog posts to Wordy.com for real-time human proofreading and copy-editing – all without leaving WordPress.', 'wordy-for-wordpress' ) . '</p>';
                $wordy_help .= '<p>' . __( '<strong>Wordy Brief</strong> - When you create a job, enter your brief to the editor before sending your post to Wordy for a free instant price quote.', 'wordy-for-wordpress' );
                $wordy_help .= '<p>' . __( '<strong>Wordy Conversation History</strong> - Once you\'ve sent your post to Wordy and confirmed the payment, you can communicate directly with your editor in the Conversation History. Simply write your message and send it.', 'wordy-for-wordpress' );
                $wordy_help .= '<p>' . __( '<strong>Wordy Summary</strong> - For all open jobs, the Summary window shows you the current status, including estimated delivery time, price and word count.', 'wordy-for-wordpress' );
                $wordy_help .= '<p>' . __( 'Once your post has been edited and returned by the editor, you can see the changes made to the post by clicking “Compare to Wordy edit” or “Load Wordy edit” beneath your post’s title field.', 'wordy-for-wordpress' );

                $screen->add_help_tab( array(
                    'id' => 'wfw-help',
                    'title' => 'Wordy',
                    'content' => $wordy_help
                ) );
            }
            if ( 'settings_page_wfw_options_page' == $screen->id ) {

                $overview_help = '<p>' . sprintf( __( 'Wordy is a professional, human, copy-editing and proofreading service that optimises the accuracy and readability of content – from Fortune 500 business reports to academic texts, website copy and your blog posts.', 'wordy-for-wordpress' ) ) . '</p>';
                $overview_help .= '<p>' . sprintf( __( 'If you do not have a Wordy account yet %1$syou can sign up for free%2$s or %3$stake a tour of Wordy%2$s before you do anything. If you have any questions or comments about Wordy, you\'re more than welcome to %4$scontact us%2$s.', 'wordy-for-wordpress' ), '<a href="https://wordy.com/accounts/signup/" target="_target">', '</a>', '<a href="https://wordy.com/tour/" target="_target">', '<a href="https://wordy.com/contact/" target="_target">' ) . '</p>';
                $account_help = '<p>' . sprintf( __( 'Enter your username (the email address you use to log in to Wordy) and an API key generated from your account.', 'wordy-for-wordpress' ) ) . '</p>';
                $account_help .= '<p>' . sprintf( __( 'If you do not have a Wordy account yet you can %1$ssign up for free%2$s.', 'wordy-for-wordpress' ), '<a href="' . WORDY_URL . '/accounts/signup/" target="_blank">', '</a>' ) . '</p>';
                $options_help = '<p>' . __( 'Choose whether you want your posts rewritten by Wordy by default. By choosing rewriting your editor will substantially reword content to improve consistency, flow and the natural use of language.', 'wordy-for-wordpress' ) . '</p>';
                $options_help .= '<p>' . __( 'Select the default language of your blog; all your posts will be edited in this language.', 'wordy-for-wordpress' ) . '</p>';

                $screen->add_help_tab( array(
                    'id' => 'wfw-overview-help',
                    'title' => __( 'Overview', 'wordy-for-wordpress' ),
                    'content' => $overview_help
                ) );
                $screen->add_help_tab( array(
                    'id' => 'wfw-account-help',
                    'title' => __( 'Account', 'wordy-for-wordpress' ),
                    'content' => $account_help
                ) );
                $screen->add_help_tab( array(
                    'id' => 'wfw-options-help',
                    'title' => __( 'Options', 'wordy-for-wordpress' ),
                    'content' => $options_help
                ) );
            }
            $screen->set_help_sidebar( '<p><strong>' . __( 'For more information:', 'wordy-for-wordpress' ) . '</strong></p><p><a href="#" target="_blank">' . __( 'Wordy for WordPress Guide', 'wordy-for-wordpress' ) . '</a></p>' );
        }
    }

    function wfw_options_page_callback() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wordy-for-wordpress' ) );
        }
        echo '<div class="wrap">';
        echo '<div id="wfw-icon" class="icon32"></div>';
        echo '<h2>' . __( 'Wordy For WordPress Settings', 'wordy-for-wordpress' ) . '</h2>';
        echo '<p>' . sprintf( __( 'Wordy for WordPress lets you seamlessly add real-time human proofreading and copy-editing to your WordPress blog posts. Editors optimise the accuracy and readability of your content making it a pleasure to read and link to. %1$sLearn more about Wordy%2$s or %3$ssign up for free%2$s.', 'wordy-for-wordpress' ), '<a href="' . WORDY_URL . '/tour/" target="_target">', '</a>', '<a href="' . WORDY_URL . '/accounts/signup/" target="_target">' ) . '</p>';
        echo '<form action="options.php" method="post">';
        settings_fields( 'wfw_options_group' );
        do_settings_sections( 'wfw_options_page' );
        echo '<p class="submit"><input name="Submit" type="submit" class="button-primary" value="' . __( 'Save Changes', 'wordy-for-wordpress' ) . '" /></p>';
        echo '</form>';
        echo '</div>';
    }

    function section_text() {
        // Placeholder for settings section (which is required for some reason)
    }

    function sanitize_callback( $input ) {

        $valid = array( );
        $valid['authorized'] = 0;

        $valid['api_username'] = sanitize_email( $input['api_username'] );
        $valid['api_key'] = $input['api_key'];
        if ( !is_email( $valid['api_username'] ) ) {
            if ( !get_settings_errors( 'wfw_options' ) ) {
                add_settings_error( 'wfw_options', 'api_error', __( 'Wordy Account Email must be a valid email address', 'wordy-for-wordpress' ) );
            }
        } else {

            require_once('class.wordy.php');
            $wordy_api = new Wordy_API_Helper( $valid['api_username'], $valid['api_key'] );
            $languages = $wordy_api->get_languages();
            if ( $languages['error'] ) {
                add_settings_error( 'wfw_options', 'api_error', $this->translate_api_messages( $languages['message']['verbose'] ) );
            } else {
                $valid['languages'] = $languages['message'];
                $valid['authorized'] = 1;
                if ( !get_settings_errors( 'wfw_options' ) ) {
                    add_settings_error( 'wfw_options', 'settings_updated', __( 'Settings Saved.', 'wordy-for-wordpress' ), 'updated' );
                }
            }
            $options = get_option( 'wfw_options' );
            $valid['language_id'] = isset( $input['language_id'] ) ? $input['language_id'] : (isset( $options['language_id'] ) ? $options['language_id'] : 1);
            $valid['content_rewrite'] = isset( $input['content_rewrite'] ) ? $input['content_rewrite'] : (isset( $options['content_rewrite'] ) ? $options['content_rewrite'] : 0);
            return $valid;
        }
    }

    function add_dashboard_widget() {
        $options = get_option( 'wfw_options' );
        if ( $this->wfw_authorized ) {
            wp_add_dashboard_widget( 'wfw_dashboard_widget', __( 'Wordy for WordPress', 'wordy-for-wordpress' ), array( &$this, 'dashboard_widget' ) );
        }
    }

    function dashboard_widget() {
        $wordy_api = $this->api_connection();
        $response = $wordy_api->get_account();
        if ( !$response['error'] ) {
            $options = get_option( 'wfw_options' );
            echo '<p>' . sprintf( __( 'Signed in as: %1$s', 'wordy-for-wordpress' ), $options['api_username'] ) . '</p>';
            echo '<p>' . sprintf( __( 'API key: %1$s', 'wordy-for-wordpress' ), $options['api_key'] ) . '</p>';
            echo '<p>' . sprintf( __( 'Language: %1$s', 'wordy-for-wordpress' ), $options['languages'][$options['language_id']] ) . '</p>';
            echo '<p>' . sprintf( __( 'Content rewrite: %1$s', 'wordy-for-wordpress' ), ($options['content_rewrite'] ? 'Yes' : 'No' ) ) . '</p>';
            echo '<p>' . sprintf( __( 'Account balance: %1$s %2$sTop up account%3$s', 'wordy-for-wordpress' ), $response['message']['balance'], '<a href="' . WORDY_URL . '/pricing/" target=_"blank">', '</a>' ) . '</p>';
        } else {
            echo '<p>Error: ' . $response['message']['verbose'] . '</p>';
            $this->deauthorize();
        }
    }

    function add_meta_boxes() {
        global $post;
        global $pagenow;
        if ( in_array( $pagenow, array( 'post.php' ) ) && in_array( $post->post_type, array( 'post', 'page' ) ) ) {
            $connection = $this->api_connection();
            $response = $connection->get_account();
            if ( $response['error'] ) {
                $this->wfw_notice = '<div class="error wfw-notice"><p>' . $response['message']['verbose'] . ' ' . '</p></div>';
                $this->deauthorize();
                $this->wfw_authorized = false;
            }

            if ( $this->wfw_authorized ) {
                $custom_fields = get_post_custom( $post->ID );
                if ( isset( $custom_fields['wordy_id'] ) ) {
                    $wordy_id = $custom_fields['wordy_id'][0];
                    $response = $connection->get_job( $wordy_id );
                    $message = $response['message'];

                    if ( !$response['error'] ) {

                        switch ( $message['status'] ) {
                            case 'acp':
                                $this->wfw_notice = '<div class="updated wfw-notice"><p>' . sprintf( __( 'The cost of editing this post is %1$s. Please %2$sConfirm Payment%3$s or %4$sDecline Payment%3$s.', 'wordy-for-wordpress' ), $message['cost'], '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '"  class="wordy-api" id="wordy-api-pay">', '</a>', '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '"  class="wordy-api" id="wordy-api-reset">' ) . '</p></div>';
                                break;

                            case 'aer':
                                $this->wfw_notice = '<div class="updated wfw-notice"><p>' . __( 'Awaiting editor response.', 'wordy-for-wordpress' ) . '</p></div>';
                                if ( isset( $_GET['message'] ) && 100 == $_GET['message'] ) {
                                    $this->wfw_notice = '<div class="updated wfw-notice"><p>' . sprintf( __( 'New job created successfully. Estimated delivery time is %1$s.', 'wordy-for-wordpress' ), $this->relativize( $message['delivery_date'], true ) ) . '</p></div>';
                                }
                                break;

                            case 'acr':
                                $new_revision = false;

                                $edited_post = $connection->get_edited( $wordy_id );
                                if ( isset( $custom_fields['wfw_revision_id'] ) ) {
                                    $revision_id = $custom_fields['wfw_revision_id'][0];
                                    $revision = get_post( $revision_id );
                                    if ( strcmp( $edited_post['message']['title'], $revision->post_title ) || strcmp( $edited_post['message']['html'], $revision->post_content ) || (isset( $edited_post['message']['excerpt'] ) && strcmp( $edited_post['message']['excerpt'], $revision->post_excerpt )) ) {
                                        $new_revision = true;
                                    }
                                } else {
                                    $new_revision = true;
                                }
                                if ( $new_revision ) {
                                    $wfw_revision = array( );
                                    $wfw_revision['post_parent'] = $post->ID;
                                    $wfw_revision['post_type'] = 'revision';
                                    $wfw_revision['post_status'] = 'inherit';
                                    $wfw_revision['post_title'] = $edited_post['message']['title'];
                                    $wfw_revision['post_name'] = $post->ID . '-revision';
                                    $wfw_revision['post_content'] = $edited_post['message']['html'];
                                    $wfw_revision['post_excerpt'] = isset( $edited_post['message']['excerpt'] ) ? $edited_post['message']['excerpt'] : '';
                                    $revision_id = wp_insert_post( $wfw_revision );
                                    update_post_meta( $post->ID, 'wfw_revision_id', $revision_id );
                                }

                                if ( strcmp( $edited_post['message']['title'], $post->post_title ) || strcmp( $edited_post['message']['html'], $post->post_content ) || (isset( $edited_post['message']['excerpt'] ) && strcmp( $edited_post['message']['excerpt'], $post->post_excerpt )) ) {
                                    $this->show_revision_buttons = $revision_id;
                                }
                                $this->wfw_notice = '<div class="updated wfw-notice"><p>' . sprintf( __( 'Wordy is awaiting your response. %1$sAccept Wordy\'s edit%2$s or %3$sreject Wordy\'s edit%2$s.', 'wordy-for-wordpress' ), '<a href="' . wp_nonce_url( 'revision.php?revision=' . $revision_id . '&action=restore', "restore-post_$post->ID|$revision_id" ) . '" class="wordy-api" id="wordy-api-confirm">', '</a>', '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '" class="wordy-api" id="wordy-api-reject">' ) . '</p></div>';
                                break;

                            case 'c':
                                $this->reset( $post->ID );
                                $this->wfw_notice = '<div class="updated wfw-notice"><p>' . __( 'Wordy edit complete.', 'wordy-for-wordpress' ) . '</p></div>';
                                break;
                        }
                    } else {
                        $this->wfw_notice = '<div class="error wfw-notice"><p>Error: ' . $message['verbose'] . ' ' . '</p></div>';
                        if ( 'permission denied' == $message['error'] ) {
                            $this->reset( $post->ID );
                        }
                    }
                }
            }
        }
                        if ( get_post_meta( $post->ID, 'wordy_id', true ) ) {
                    add_meta_box( '-wfw-conversation-meta-box', __( 'Wordy Conversation History', 'wordy-for-wordpress' ), array( &$this, 'wfw_conversation_meta_box' ), 'post', 'normal', 'high' );
                    add_meta_box( 'wfw-summary-meta-box', __( 'Wordy Summary', 'wordy-for-wordpress' ), array( &$this, 'wfw_summary_meta_box' ), 'post', 'side', 'high' );
                    add_meta_box( '-wfw-conversation-meta-box', __( 'Wordy Conversation History', 'wordy-for-wordpress' ), array( &$this, 'wfw_conversation_meta_box' ), 'page', 'normal', 'high' );
                    add_meta_box( 'wfw-summary-meta-box', __( 'Wordy Summary', 'wordy-for-wordpress' ), array( &$this, 'wfw_summary_meta_box' ), 'page', 'side', 'high' );
                } else {
                    add_meta_box( 'wfw-brief-meta-box', __( 'Wordy Brief', 'wordy-for-wordpress' ), array( &$this, 'wfw_brief_meta_box' ), 'post', 'side', 'high' );
                    add_meta_box( 'wfw-brief-meta-box', __( 'Wordy Brief', 'wordy-for-wordpress' ), array( &$this, 'wfw_brief_meta_box' ), 'page', 'side', 'high' );
                }
    }

    function translate_api_messages( $api_message ) {
        $wfw_messages = array(
            'awc' => __( 'Awaiting word count', 'wordy-for-wordpress' ),
            'acp' => __( 'Awaiting customer payment', 'wordy-for-wordpress' ),
            'aer' => __( 'Awaiting editor response', 'wordy-for-wordpress' ),
            'acr' => __( 'Awaiting customer response', 'wordy-for-wordpress' ),
            'c' => __( 'Complete', 'wordy-for-wordpress' ),
            'no matching key' => __( 'We can\'t find that API key.', 'wordy-for-wordpress' ),
            'no matching user' => __( 'We can\'t find that API username.', 'wordy-for-wordpress' ),
            'job couldn\'t be paid off' => __( 'You do not have sufficient credit.', 'wordy-for-wordpress' )
        );
        if ( array_key_exists( strtolower( $api_message ), $wfw_messages ) ) {
            return $wfw_messages[strtolower( $api_message )];
        } else {
            return $api_message;
        }
    }

    function wfw_summary_meta_box( $post ) {

        $custom_fields = get_post_custom( $post->ID );

        if ( isset( $custom_fields['wordy_id'] ) ) {
            date_default_timezone_set( $this->timezone );
            $wordy_id = $custom_fields['wordy_id'][0];
            $wordy_api = $this->api_connection();
            $response = $wordy_api->get_job( $wordy_id );
            $message = $response['message'];
            echo '<div class="misc-pub-section">' . __( 'Status', 'wordy-for-wordpress' ) . ': <span style="font-weight: bold;">' . $this->translate_api_messages( $message['status'] ) . '</span></div>';
            echo '<div class="misc-pub-section">' . __( 'Language', 'wordy-for-wordpress' ) . ': ' . $message['source_language_name'][1] . '</div>';
            echo '<div class="misc-pub-section">' . __( 'Rewriting', 'wordy-for-wordpress' ) . ': ' . ($message['intrusive_editing'] ? 'Yes' : 'No') . '</div>';
            echo '<div class="misc-pub-section">' . __( 'Source word count', 'wordy-for-wordpress' ) . ': ' . $message['source_word_count'] . '</div>';
            echo '<div class="misc-pub-section">' . __( 'Cost', 'wordy-for-wordpress' ) . ': ' . $message['cost'] . '</div>';
            echo '<div class="misc-pub-section">' . __( 'Created', 'wordy-for-wordpress' ) . ': ' . date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message['created'] ) . '</div>';
            echo '<div class="misc-pub-section">' . __( 'Delivery date', 'wordy-for-wordpress' ) . ': ' . date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message['delivery_date'] ) . '</div>';
            echo '<div class="misc-pub-section"><a href="' . WORDY_URL . $message['display_url'] . '" target="_blank">' . sprintf( __( 'View job %d on Wordy.com', 'wordy-for-wordpress' ), $message['id'] ) . '</a></div>';
            echo '<div style="clear:both;"></div>';
        }
    }

    function wfw_brief_meta_box( $post ) {
        echo '<span class="description wfw-placeholder-alternative">' . __( 'Your comments or instructions to the editor', 'wordy-for-wordpress' ) . '</span>';
        echo '<textarea name="brief" style="width: 100%;" placeholder="' . __( 'Your comments or instructions to the editor', 'wordy-for-wordpress' ) . '"></textarea>';
        echo '<div style="text-align:right"><input name="send_to_wordy" id="send_to_wordy" type="submit" class="button-primary" value="' . __( 'Send to Wordy', 'wordy-for-wordpress' ) . '"></div>';
    }

    function wfw_conversation_meta_box( $post ) {
        $custom_fields = get_post_custom( $post->ID );
        if ( isset( $custom_fields['wordy_id'] ) ) {
            echo '<div class="wfw-messenger">';
            echo '<div class="wfw-messages"></div>';
            echo '<input type="text" name="conversation_update" value="" placeholder="' . __( 'Write message', 'wordy-for-wordpress' ) . '" id="wfw-update-conversation-text" />';
            echo '<div style="text-align: right; clear: both;"><a href="#" class="button-primary" id="wfw-update-conversation">' . __( 'Send message', 'wordy-for-wordpress' ) . '</a></div>';
            echo '</div>';
        }
    }

    function send_to_wordy( $post_id ) {

        if ( !$this->wfw_authorized ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( !current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_REQUEST['send_to_wordy'] ) ) {
            if ( !wp_is_post_revision( $post_id ) ) {
                $connection = $this->api_connection();
                $options = get_option( 'wfw_options' );
                $connection->set_language_id( $options['language_id'] );
                $connection->set_intrusive_editing( $options['content_rewrite'] ? 'true' : 'false'  );
                $connection->set_user_agent( WFW_USER_AGENT );
                $post = get_post( $post_id );
                if ( in_array( $_POST['post_type'], array( 'post', 'page' ) ) ) {
                    $brief = $_POST['brief'];
                    $json = array( );
                    $json['title'] = $post->post_title;
                    $json['html'] = $post->post_content;
                    if ( $post->post_excerpt )
                        $json['excerpt'] = $post->post_excerpt;

                    $response = $connection->create_job( array( 'brief' => $brief, 'json' => $json ) );
                    $message = $response['message'];
                    update_post_meta( $post_id, 'wordy_id', $message['id'] );
                }
            }
        }
    }

    function api_connection() {
        require_once('class.wordy.php');
        $options = get_option( 'wfw_options' );
        $connection = new Wordy_API_Helper( $options['api_username'], $options['api_key'] );
        return $connection;
    }

    function get_conversation_callback() {
        if ( check_ajax_referer( 'wfwnonce', 'security' ) ) {
            $wordy_api = $this->api_connection();
            $post_id = $_GET['post_id'];

            $custom_fields = get_post_custom( $post_id );
            $wordy_id = $custom_fields['wordy_id'][0];
            $conversation = $wordy_api->get_conversation( $wordy_id );
            foreach ( $conversation['message'] as $part ) {
                if ( !isset( $client ) ) {
                    $client = $part['user'];
                }
                $party = ($client == $part['user'] ? 'wfw-client' : 'wfw-editor');
                echo '<div class="' . $party . '">';
                echo '<div class="wfw-quote">';
                echo '<p>' . $part['message'] . '<br />';
                echo '<span class="wfw-timestamp">' . sprintf( __( '%1$s on %2$s', 'wordy-for-wordpress' ), '<span class="wfw-name">' . $part['user'] . '</span>', date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $part['created'] ) ) . '</span></p>';
                echo '</div>';
                echo '</div>';
            }
        }
        die();
    }

    function update_conversation_callback() {
        if ( check_ajax_referer( 'wfwnonce', 'security' ) ) {
            $wordy_api = $this->api_connection();
            $update = $_GET['update'];
            $post_id = $_GET['post_id'];
            $custom_fields = get_post_custom( $post_id );
            $wordy_id = $custom_fields['wordy_id'][0];
            $wordy_api->update_conversation( $wordy_id, $update );
        }
        die();
    }

    function wordy_api_callback() {
        if ( check_ajax_referer( 'wfwnonce', 'security' ) ) {
            $wordy_api = $this->api_connection();
            $post_id = $_GET['post_id'];
            $command = $_GET['command'];
            $custom_fields = get_post_custom( $post_id );
                $wordy_id = $custom_fields['wordy_id'][0];


            switch ( $command ) {

                case 'wordy-api-pay':
                    $response = $wordy_api->pay_job( $wordy_id );
                    if ( $response['error'] ) {
                        echo json_encode( array( 'message' => '<p>' . $this->translate_api_messages( $response['message']['verbose'] ) . '</p>' ) );
                    } else {
                        echo json_encode( array( 'message' => 100 ) );
                    }
                    break;

                case 'wordy-api-confirm':
                    $wordy_api = $wordy_api->confirm_job( $wordy_id );
                    break;

                case 'wordy-api-reject':
                    $response = $wordy_api->reject_job( $wordy_id );
                    echo json_encode( array( 'message' => 0 ) );
                    break;

                case 'wordy-api-reset':
                    $this->reset( $post_id );
                    break;
            }
        }
        die();
    }

    function admin_styles() {
        wp_enqueue_style( 'wfw-style', plugins_url( '/', __FILE__ ) . 'wordy-for-wordpress.css', array( ), WFW_VERSION );
    }

    function admin_scripts() {
        if ( $this->wfw_authorized ) {
            global $pagenow;
            global $post;
            $pages = array( 'post-new.php', 'post.php', 'revision.php' );
            if ( in_array( $pagenow, $pages ) ) {
                $data = array( );
                $custom_fields = get_post_custom( $post->ID );
                if ( isset( $custom_fields['wfw_revision_id'] ) ) {
                    $data['revisionID'] = $custom_fields['wfw_revision_id'][0];
                }

                $data['postID'] = $post->ID;
                $data['wfwnonce'] = wp_create_nonce( 'wfwnonce' );
                wp_enqueue_script( 'wfw-scripts', plugins_url( '/', __FILE__ ) . 'wordy-for-wordpress.js', array( 'jquery' ), '1.0' );
                wp_localize_script( 'wfw-scripts', 'wfwObject', $data );
            }
        }
    }

    function get_wordy_id( $post_id ) {
        $custom_fields = get_post_custom( $post_id );
        $wordy_id = isset( $custom_fields['wordy_id'] ) ? $custom_fields['wordy_id'][0] : 0;
        return $wordy_id;
    }

    function column_headings( $defaults ) {
        global $typenow;
        if ( !in_array( $typenow, array( 'post', 'page' ) ) )
            return $defaults;
        if ( $this->wfw_authorized ) {
            $wordy_api = $this->api_connection();
            $this->job_list = $wordy_api->get_jobs();
            $defaults['wordy_status'] = __( 'Wordy Status', 'wordy-for-wordpress' );
        }
        return $defaults;
    }

    function custom_column( $column_name, $id ) {
        if ( 'wordy_status' == $column_name ) {
            $wordy_id = $this->get_wordy_id( $id );
            if ( is_array( $this->job_list['message'] ) && in_array( $wordy_id, $this->job_list['message'] ) ) {
                $connection = $this->api_connection();
                $wordy_api = $connection->get_job( $wordy_id );
                $message = $wordy_api['message'];
                if ( $wordy_api['error'] ) {
                    echo $message['verbose'];
                    $this->deauthorize();
                } else {
                    $response = $wordy_api['message'];
                    echo $this->translate_api_messages( $message['status'] );
                }
            } else {
                echo '-';
            }
        }
    }

    function deauthorize() {
        $options = get_option( 'wfw_options' );
        $options['authorized'] = 0;
        update_option( 'wfw_options', $options );
    }

    function post_row_actions( $actions, $post ) {
        if ( $this->wfw_authorized ) {
            $wordy_id = $this->get_wordy_id( $post->ID );
            if ( !in_array( $wordy_id, $this->job_list['message'] ) ) {
                $actions['wordy'] = "<a href='" . admin_url( '' ) . "'>" . __( 'Send to Wordy', 'wordy-for-wordpress' ) . "</a>";
            }
        }
        return $actions;
    }

    function set_sample_permalink_html( $sample, $id, $new_title, $new_slug ) {
        global $post;
        if ( in_array( get_post_type(), array( 'post', 'page' ) ) ) {
            if ( isset( $this->show_revision_buttons ) ) {
                $sample .= '<span id="load-wordy-edit"><a href="' . wp_nonce_url( 'revision.php?action=restore&revision=' . $this->show_revision_buttons, "restore-post_$post->ID|$this->show_revision_buttons" ) . '" class="button-primary">' . __( 'Load Wordy edit', 'wordy-for-wordpress' ) . '</a></span>
                        <span id="diff-wordy-edit"><a href="revision.php?action=diff&post_type=post&right=' . $post->ID . '&left=' . $this->show_revision_buttons . '" class="button">' . __( 'Compare to Wordy edit', 'wordy-for-wordpress' ) . '</a></span>' . "\n";
            }
        }
        return $sample;
    }

    function relativize( $timestamp ) {
        $seconds = ($timestamp - time());
        $hours = floor( $seconds / (60 * 60) );
        $divisor_for_minutes = $seconds % (60 * 60);
        $minutes = floor( $divisor_for_minutes / 60 );
        $string = ($hours ? $hours . ' hour' . (1 == $hours ? 's' : '') : '') . ' ';
        $string .= ($minutes ? $minutes . ' minutes' . (1 == $minutes ? 's' : '') : '');
        return $string;
    }

}

$wfw = new Wordy_For_WordPress();