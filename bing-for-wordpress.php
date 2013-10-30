<?php
/*
 Plugin Name: Bing for WordPress
 Plugin URI: 
 Description: Replaces WordPress's default search engine with Bing.com
 Version: 0.5
 Author: Garrick van Buren
 Author URI: http://garrickvanburen.com
 */


class BingForWordPressPage {
    private $options;

    public function __construct(){
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    public function add_plugin_page() {
        // This page will be under "Settings"
        add_options_page(
            'Bing for WordPress', 
            'Bing for WordPress', 
            'manage_options', 
            'bing-for-wordpress-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        // Set class property
        $this->options = get_option( 'bing_for_wordpress' );
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>Bing for WordPress</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'bing_for_wordpress_group' );   
                do_settings_sections( 'bing-for-wordpress-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {        
        register_setting(
            'bing_for_wordpress_group', // Option group
            'bing_for_wordpress' // Option name
        );

        add_settings_section(
            'azure_settings', // ID
            'Azure Settings', // Title
            array( $this, 'print_azure_section_info' ), // Callback
            'bing-for-wordpress-admin' // Page
        );  

        add_settings_section(
            'search_settings', // ID
            'Search Settings', // Title
            array( $this, 'print_search_section_info' ), // Callback
            'bing-for-wordpress-admin' // Page
        );

        add_settings_field(
            'domain', // ID
            'Domain', // Title 
            array( $this, 'domain_callback' ), // Callback
            'bing-for-wordpress-admin', // Page
            'search_settings' // Section           
        );

        add_settings_field(
            'account_key', // ID
            'Account Key', // Title 
            array( $this, 'account_key_callback' ), // Callback
            'bing-for-wordpress-admin', // Page
            'azure_settings' // Section           
        );           
    }

    public function print_search_section_info() {
        print 'Search results should be restricted to:';
    }

    public function print_azure_section_info() {
		print '<ol><li>Sign into <a href="https://datamarket.azure.com">https://datamarket.azure.com</a> with your Microsoft account (live.com, etc)</li>
				<li>Select a <a href="https://datamarket.azure.com/dataset/bing/searchweb">subscription level to the Bing Search API</a></li>
				<li>Once you&#39;ve completed the subscription process, visit <a href="https://datamarket.azure.com/account/keys">https://datamarket.azure.com/account/keys</a> and generate an account key</li>
				<li>Copy & paste the Account Key in the field below.</li></ol>';
    }

    public function domain_callback(){
        printf(
            '<input type="text" id="domain" name="bing_for_wordpress[domain]" value="%s" />',
            esc_attr( $this->options['domain'])
        );
    }

    public function account_key_callback(){
        printf(
            '<input type="text" id="account_key" name="bing_for_wordpress[account_key]" value="%s" />',
            esc_attr( $this->options['account_key'])
        );
    }
}

if( is_admin() )
    $my_settings_page = new BingForWordPressPage();


// BingSearch class
// Originally written by Ayesh Karunaratne
// https://github.com/Ayesh/BingSearchAPI
class BingSearch {
  protected $appid = '';
  public $base_url = 'https://api.datamarket.azure.com/Bing/SearchWeb/v1/';
  public $skip = 0;
  public $query = '';
  public $top = 40;
  protected $results = 40;
  public $fetchers = array('curl', 'file_get_contents');
  public $fetcher = 'file_get_contents';
  protected $next_possible = TRUE;
  public $count = 0;
  
  function __construct($search = '', $start = 0, $results = 40, $appid = NULL) {
    $this->query = $search;
    $this->skip = $start;
    $this->top = $results;
    if (!is_null($appid)) {
      $this->appid = $appid;
    }
  }
  function set_query($search){
    $this->query = $search;
  }
  
  function set_appid($appid) {
    $this->appid = $appid;
  }
  function set_resultcount($results) {
    $this->results = $results;
  }
  
  function set_startpoint($start = 0) {
    $this->skip = $start;
  }
  function set_fetcher($method) {
    if (method_exists($this, $method) && in_array($method, $this->fetchers)) {
      $this->fetcher = $method;
    }
  }
  
  protected function build_url() {
    $url = $this->base_url . 'Web?$format=json&Query=';
    $url .= urlencode("'{$this->query}'");
    $url .= "&\$top={$this->results}"; 
    if ($this->skip > 0) {
      $url .= "&\$skip={$this->skip}";
    }
    return $url;
  }
  
  function debug_url($return = FALSE) {
    if ($return) {
      return $this->build_url();
    }
    if (function_exists('dpm') && function_exists('krumo')) { 
      dpm($this->build_url()); 
    }
    elseif (function_exists('krumo')) {
      krumo($this->build_url());
    }
    else {
      print '<pre>' . $this->build_url() . '</pre>';
    }
  }
  
  function build_context() {
    
    return $context;
  }
  
  function next_page() {
    $this->skip += $this->top;
  }
  
  function further() {
    return $this->next_possible;
  }
  
  protected function fetch() {
    $url = $this->build_url();
    return $this->{$this->fetcher}($url, $this->appid);
  }
  
  function search() {
    $results = json_decode($this->fetch());
    $this->next_possible = isset($results->d->__next);
    if (!empty($results->d->results)) {
      $this->count += count($results->d->results);
      return $results->d->results;
    }
    return FALSE;
  }
  
  protected function file_get_contents($url, $appid) {
    $url = $this->build_url();
    $context = stream_context_create(
      array(
        'http' => array(
          'request_fulluri' => true,       
          'header'  => "Authorization: Basic " . base64_encode($appid . ':' . $appid)
        )
      )
    );
    return file_get_contents($url, 0, $context);
  }
  
  protected function curl($url, $appid) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$appid:$appid");
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }
}


function bing_for_wordpress_template_redirect() {
	// thanks to the Better Search plugin for the idea:  http://wordpress.org/extend/plugins/better-search/
	if ( (stripos($_SERVER['REQUEST_URI'], '?s=') === FALSE) && (stripos($_SERVER['REQUEST_URI'], '/search/') === FALSE) && (!is_search()) ) {
		return;
	}
	$s = apply_filters('the_search_query', get_search_query());
		
	// change status code to 200 OK since /search/ returns status code 404
	@header("HTTP/1.1 200 OK",1);
	@header("Status: 200 OK", 1);
	
	// If there is a template file then we use it
	$exists = file_exists(get_stylesheet_directory() . '/bing-for-wordpress-template.php');
	if ($exists) {
		include_once(get_stylesheet_directory() . '/bing-for-wordpress-template.php');
		exit;
	}
	get_header(); ?> 
	<div id="content" class="bing_for_wordpress_results_page hentry article">
		<h1 class="page-title"><a href="<?php echo home_url(); ?>/?<?php echo $s; ?>">Search Results for: <span><?php echo $s; ?></span></a></h1>

		<?php 
		$results = bing_for_wordpress_search($s);
		foreach($results as $r) { ?>
			<?php if ( !preg_match('/\/feed\/$/', $r->Url) ) { ?>
				<div class="hentry story result">
					<h2 class="entry-title"><a href="<?php echo $r->Url; ?>"><?php  echo $r->Title; ?></a></h2>
					<p><?php echo $r->Description; ?></p>
				</div>
			<?php } 
		 } ?>
	</div>
	</div>
	</div>
	<?php get_sidebar(); ?>
	<?php get_footer();
	exit;
}

add_action('template_redirect', 'bing_for_wordpress_template_redirect', 1);


function bing_for_wordpress_search($s) {
	$bing_for_wordpress_options = get_option('bing_for_wordpress', array() );
	$bing = new BingSearch("site:" . $bing_for_wordpress_options['domain'] . " " .  $s, 0,5000,$bing_for_wordpress_options['account_key']);
	$results = $bing->search();	
	return $results;
}
