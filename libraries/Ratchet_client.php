<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Namespaces
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

/**
 * @package   CodeIgniter Ratchet WebSocket Library: Main class
 * @category  Libraries
 * @author    Romain GALLIEN <romaingallien.rg@gmail.com>
 * @license   http://opensource.org/licenses/MIT > MIT License
 * @link      https://github.com/romainrg
 *
 * CodeIgniter library who allow you to make powerfull applications with realtime interactions by using Websocket technology and Ratchetphp
 */
class Ratchet_client
{
    /**
     * CI Super Instance
     * @var array
     */
    private $CI;

    /**
     * Default host var
     * @var string
     */
    public $host = null;

    /**
     * Default host var
     * @var string
     */
    public $port = null;

    /**
     * Default auth var
     * @var bool
     */
    public $auth = false;

    /**
     * Default debug var
     * @var bool
     */
    public $debug = false;

    /**
     * Default local_cert_path var
     * @var string
     */
    public $local_cert_path = null;

    /**
     * Default local_pk_path var
     * @var string
     */
    public $local_pk_path = null;

    /**
     * Default allow_self_signed var
     * @var bool
     */
    public $allow_self_signed = false;

    /**
     * Default verify_peer var
     * @var bool
     */
    public $verify_peer = false;

    /**
     * Auth callback informations
     * @var array
     */
    public $callback = array();

    /**
     * Config vars
     * @var array
     */
    protected $config = array();

    /**
     * Define allowed callbacks
     * @var array
     */
    protected $callback_type = array('auth', 'event');

    /**
     * Class Constructor
     * @method __construct
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @param  array $config Configuration
     * @return void
     */
    public function __construct(array $config = array())
    {
        // Load the CI instance
        $this->CI = & get_instance();

        // Load the class helper
        $this->CI->load->helper('ratchet_client');

        // Define the config vars
        $this->config = (!empty($config)) ? $config : array();

        // Config file verification
        if (empty($this->config)) {
            output('fatal', 'The configuration file does not exist');
        }

        // Assign HOST value to class var
        $this->host = (!empty($this->config['ratchet_client']['host'])) ? $this->config['ratchet_client']['host'] : '';

        // Assign PORT value to class var
        $this->port = (!empty($this->config['ratchet_client']['port'])) ? $this->config['ratchet_client']['port'] : '';

        // Assign AUTH value to class var
        $this->auth = (!empty($this->config['ratchet_client']['auth'] && $this->config['ratchet_client']['auth'])) ? true : false;

        // Assign DEBUG value to class var
        $this->debug = (!empty($this->config['ratchet_client']['debug'] && $this->config['ratchet_client']['debug'])) ? true : false;

        // Assign local_cert_path value to class var
        $this->local_cert_path = (!empty($this->config['ratchet_client']['local_cert_path'])) ? $this->config['ratchet_client']['local_cert_path'] : '';

        // Assign local_pk_path value to class var
        $this->local_pk_path = (!empty($this->config['ratchet_client']['local_pk_path'])) ? $this->config['ratchet_client']['local_pk_path'] : '';

        // Assign allow_self_signed value to class var
        $this->allow_self_signed = (!empty($this->config['ratchet_client']['allow_self_signed'] && $this->config['ratchet_client']['allow_self_signed'])) ? true : false;

        // Assign verify_peer value to class var
        $this->verify_peer = (!empty($this->config['ratchet_client']['verify_peer'] && $this->config['ratchet_client']['verify_peer'])) ? true : false;
    }

    /**
     * Launch the server
     * @method run
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @return string
     */
    public function run()
    {
        // Initiliaze all the necessary class
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new Server()
                )
            ),
            $this->port,
            $this->host
        );

        // Run the socket connection !
        $server->run();
    }

    /**
     * Launch the server in secure mode
     * @method wss_run
     * @author Ale Felix <ale.felix.mail@gmail.com>
     * @return string
     */
    public function wss_run()
    {
        $app = new HttpServer(
            new WsServer(
                new Server()
            )
        );

        $loop = \React\EventLoop\Factory::create();

        $secure_websockets = new \React\Socket\Server($this->host . ':' . $this->port, $loop);
        $secure_websockets = new \React\Socket\SecureServer($secure_websockets, $loop, [
            'local_cert' => $this->local_cert_path,
            'local_pk' => $this->local_pk_path,
            'allow_self_signed' => $this->allow_self_signed,
            'verify_peer' => $this->verify_peer
        ]);

        $secure_websockets_server = new IoServer($app, $secure_websockets, $loop);
        $secure_websockets_server->run();
    }

    /**
     * Define a callback to use auth or event callback
     * @method set_callback
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @param  array             $callback
     * @return void
     */
    public function set_callback($type = null, array $callback = array())
    {
        // Check if we have an authorized callback given
        if (!empty($type) && in_array($type, $this->callback_type)) {

            // Verify if the method does really exists
            if (is_callable($callback)) {

                // Register callback as class var
                $this->callback[$type] = $callback;
            } else {
                output('fatal', 'Method '.$callback[1].' is not defined');
            }
        }
    }
}

 /**
  * @package   CodeIgniter Ratchet WebSocket Library: Server class
  * @category  Libraries
  * @author    Romain GALLIEN <romaingallien.rg@gmail.com>
  * @license   http://opensource.org/licenses/MIT > MIT License
  * @link      https://github.com/romainrg
  *
  * CodeIgniter library who allow you to make powerfull applications with realtime interactions by using Websocket technology and Ratchetphp
  */
class Server implements MessageComponentInterface
{
    /**
     * List of connected clients
     * @var array
     */
    protected $clients;

    /**
    * List of subscribers (associative array)
    * @var array
    */
    protected $subscribers = array();

    /**
     * Class constructor
     * @method __construct
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     */
    public function __construct()
    {
        // Load the CI instance
        $this->CI = & get_instance();

        // Initialize object as SplObjectStorage (see PHP doc)
        $this->clients = new SplObjectStorage;

        // // Check if auth is required
        if ($this->CI->ratchet_client->auth && empty($this->CI->ratchet_client->callback['auth'])) {
            output('fatal', 'Authentication callback is required, you must set it before run server, aborting..');
        }

        // Output
        if ($this->CI->ratchet_client->debug) {
            output('success', 'Running server on host '.$this->CI->ratchet_client->host.':'.$this->CI->ratchet_client->port);
        }

        // Output
        if (!empty($this->CI->ratchet_client->callback['auth']) && $this->CI->ratchet_client->debug) {
            output('success', 'Authentication activated');
        }
    }

    /**
     * Event trigerred on new client event connection
     * @method onOpen
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @param  ConnectionInterface $connection
     * @return string
     */
    public function onOpen(ConnectionInterface $connection)
    {
        // Add client to global clients object
        $this->clients->attach($connection);

        // Output
        if ($this->CI->ratchet_client->debug) {
            output('info', 'New client connected as ('.$connection->resourceId.')');
        }
    }

    /**
     * Event trigerred on new message sent from client
     * @method onMessage
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @param  ConnectionInterface $client
     * @param  string              $message
     * @return string
     */
    public function onMessage(ConnectionInterface $client, $message)
    {
        // Broadcast var
        $broadcast = false;

        // Check if received var is json format
        if (valid_json($message)) {

            // If true, we have to decode it
            $datas = json_decode($message);
        }

        // Once we decoded it, we check look for global broadcast
        $broadcast = (!empty($datas->broadcast) and $datas->broadcast == true) ? true : false;

        // Count real clients numbers (-1 for server)
        $clients = count($this->clients) - 1;

        // Here we have to reassign the client ressource ID, this will allow us to send message to specified client.
        if (!empty($datas->user_id) && $datas->user_id !== $client->resourceId) {

            // At this moment we have to check if we have authent callback defined
            if (!empty($this->CI->ratchet_client->callback['auth']) && empty($client->subscriber_id)) {

                // Call user personnal callback
                $auth = call_user_func_array($this->CI->ratchet_client->callback['auth'], array($datas));

                // Verify authentication
                if (empty($auth) or !is_integer($auth)) {
                    output('error', 'Client ('.$client->resourceId.') authentication failure');

                    // Closing client connexion with error code "CLOSE_ABNORMAL"
                    $client->close(1006);
                }

                // Add UID to associative array of subscribers
                $client->subscriber_id = $auth;

                // Output
                if ($this->CI->ratchet_client->debug) {
                    output('success', 'Client ('.$client->resourceId.') authentication success');
                }
            }
        }

        // Now this is the management of messages destinations, at this moment, 4 possibilities :
        // 1 - Message is not an array OR message has no destination (broadcast to everybody except us)
        // 2 - Message is an array and have destination (broadcast to single user)
        // 3 - Message is an array and don't have specified destination (broadcast to everybody except us)
        // 4 - Message is an array and we wan't to broadcast to ourselves too (broadcast to everybody)
        if (!empty($message)) {

            // We look arround all clients
            foreach ($this->clients as $user) {

                // Broadcast to single user
                if (!empty($datas->recipient_id)) {
                    if (isset($user->subscriber_id) && $user->subscriber_id == $datas->recipient_id) {
                        $this->send_message($user, $message, $client);
                        // We don't break the loop here because the same user could have multiple clients
                    }
                } else {
                    // Broadcast to everybody
                    if ($broadcast) {
                        $this->send_message($user, $message, $client);
                    } else {
                        // Broadcast to everybody except us
                        if ($client !== $user) {
                            $this->send_message($user, $message, $client);
                        }
                    }
                }
            }
        }
    }

    /**
     * Event triggered when connection is closed (or user disconnected)
     * @method onClose
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @param  ConnectionInterface $connection
     * @return string
     */
    public function onClose(ConnectionInterface $connection)
    {
        // Output
        if ($this->CI->ratchet_client->debug) {
            output('info', 'Client ('.$connection->resourceId.') disconnected');
        }

        // Detach client from SplObjectStorage
        $this->clients->detach($connection);
    }

    /**
     * Event trigerred when error occured
     * @method onError
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @param  ConnectionInterface $connection
     * @param  Exception           $e
     * @return string
     */
    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        // Output
        if ($this->CI->ratchet_client->debug) {
            output('fatal', 'An error has occurred: '.$e->getMessage());
        }

        // We close this connection
        $connection->close();
    }

    /**
     * Function to send the message
     * @method send_message
     * @author Romain GALLIEN <romaingallien.rg@gmail.com>
     * @param  array        $user    User to send
     * @param  array        $message Message
     * @param  array        $client  Sender
     * @return string
     */
    protected function send_message($user = array(), $message = array(), $client = array())
    {
        // Send the message
        $user->send($message);

        // We have to check if event callback must be called
        if (!empty($this->CI->ratchet_client->callback['event'])) {

            // At this moment we have to check if we have authent callback defined
            call_user_func_array($this->CI->ratchet_client->callback['event'], array((valid_json($message) ? json_decode($message) : $message)));

            // Output
            if ($this->CI->ratchet_client->debug) {
                output('info', 'Callback event "'.$this->CI->ratchet_client->callback['event'][1].'" called');
            }
        }

        // Output
        if ($this->CI->ratchet_client->debug) {
            output('info', 'Client ('.$client->resourceId.') send \''.$message.'\' to ('.$user->resourceId.')');
        }
    }
}
