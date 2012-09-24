<?php

class Wordy_API_Helper {
    const api_url = 'https://wordy.com/api/1.0/';
    private $email;
    private $api_key;
    private $user_agent = '';
    private $language_id = 1; // defaults to English (UK)
    private $intrusive_editing = 'false';
    private $callback_url = null;

    /**
     *
     * @param string $email
     * @param string $api_key 
     */
    function __construct( $email = '', $api_key = '' ) {
        $this->email = $email;
        $this->api_key = $api_key;
        $this->check_requirements();
    }

    /**
     * Check for cURL
     */
    private function check_requirements() {
        if ( !function_exists( 'curl_init' ) )
            die( 'cURL is required' );
    }

    /**
     *
     * @param string $email 
     */
    public function set_email( $email = null ) {
        $this->email = $email;
    }

    /**
     *
     * @param string $api_key 
     */
    public function set_api_key( $api_key = null ) {
        $this->api_key = $api_key;
    }

    /**
     *
     * @param integer $language_id 
     */
    public function set_language_id( $language_id = null ) {
        $this->language_id = $language_id;
    }

    /**
     *
     * @param string $user_agent 
     */
    public function set_user_agent( $user_agent = null ) {
        $this->user_agent = $user_agent;
    }

    /**
     *
     * @param boolean $intrusive_editing 
     */
    public function set_intrusive_editing( $intrusive_editing = null ) {
        $this->intrusive_editing = $intrusive_editing;
    }

    /**
     *
     * @param string $callback_url 
     */
    public function set_callback_url( $callback_url = null ) {
        $this->callback_url = $callback_url;
    }

    /**
     *
     * @return array 
     */
    public function get_account() {
        return $this->send_request( 'account' );
    }

    /**
     *
     * @return array 
     */
    public function get_jobs() {
        return $this->send_request( 'job' );
    }

    /**
     *
     * @return array 
     */
    public function get_languages() {
        return $this->send_request( 'languages' );
    }

    /**
     *
     * @param integer $id
     * @return array 
     */
    public function get_job( $id ) {
        return $this->send_request( 'job/' . $id );
    }

    /**
     *
     * @param integer $id
     * @return array 
     */
    public function get_original( $id ) {
        return $this->send_request( 'job/' . $id . '/source' );
    }

    /**
     *
     * @param integer $id
     * @return array 
     */
    public function get_edited( $id ) {
        return $this->send_request( 'job/' . $id . '/target' );
    }

    /**
     *
     * @param integer $id
     * @return array 
     */
    public function get_conversation( $id ) {
        return $this->send_request( 'job/' . $id . '/conversation' );
    }

    /**
     *
     * @param integer $id
     * @param string $message
     * @return array 
     */
    public function update_conversation( $id, $message ) {
        return $this->send_request( 'job/' . $id . '/conversation', 'POST', array( 'message' => $message ) );
    }

    /**
     *
     * @param integer $id
     * @return array 
     */
    public function pay_job( $id ) {
        return $this->send_request( 'job/' . $id . '/pay', 'POST' );
    }

    /**
     *
     * @param integer $id
     * @return array 
     */
    public function confirm_job( $id ) {
        return $this->send_request( 'job/' . $id . '/confirm', 'POST' );
    }

    /**
     *
     * @param integer $id
     * @return array 
     */
    public function reject_job( $id ) {
        return $this->send_request( 'job/' . $id . '/reject', 'POST' );
    }

    /**
     *
     * @param array $content_array
     * @return array 
     */
    public function create_job( $content_array ) {
        $parameters = array( );
        $parameters['language_id'] = $this->language_id;
        $parameters['intrusive_editing'] = $this->intrusive_editing;
        $parameters['callback_url'] = $this->callback_url;
        foreach ( $content_array as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = json_encode( $value );
            }
            if ( 'fileToUpload' == $key ) {
                if ( !is_file( $value ) ) {
                    die( $value . ' is not a file' );
                }
                $value = '@' . $value;
            }
            $parameters[$key] = $value;
        }
        return $this->send_request( 'job/create', 'POST', $parameters );
    }

    /**
     *
     * @return array 
     */
    public function get_callback() {
        $data = file_get_contents( 'php://input' );
        return json_decode( $data, true );
    }

    /**
     *
     * @param string $command
     * @param string $method
     * @param string $parameters
     * @return array The first element has key 'error', and value of true or false.
     * The second element has key 'message', and value of an array. 
     * If error is true, the message array will consist of 'error' and 'verbose'
     * explanations of the error. Otherwise, the message will hold the response
     * from the API.
     * 
     *  
     */
    protected function send_request( $command, $method = 'GET', $parameters = '' ) {
        $url = self::api_url . $command . '/';
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
        curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
        curl_setopt( $ch, CURLOPT_USERPWD, $this->email . ':' . $this->api_key );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
        if ( 'POST' == $method && $parameters )
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $parameters );
        $results = curl_exec( $ch );
        $response = array( );
        if ( $results === false ) {
            $response['error'] = true;
            $response['message'] = array( 'error' => curl_errno( $ch ), 'verbose' => curl_error( $ch ) );
        } else {
            $info = curl_getinfo( $ch );
            $response['error'] = (200 != $info['http_code']);
            $response['message'] = json_decode( $results, true );
        }
        curl_close( $ch );
        return $response;
    }

}