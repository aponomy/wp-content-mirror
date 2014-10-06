<?php
/*
Plugin Name: Content Mirror
Plugin URI: http://klasehnemark.com
Description: You can display content from one page or post on another by just selecting the original post in the editor. When the original post is updated, so is the mirrored one. This also works with post from other sites in a multisite configuration. 
Author: Klas Ehnemark
Version: 2.0
Author URI: http://klasehnemark.com

Copyright (C) 2011-2014 Klas Ehnemark (http://klasehnemark.com)

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

More information can be found at http://klasehnemark.com/wordpress-plugins
*/


add_action('activated_plugin','content_mirror_save_error');

function content_mirror_save_error(){

    update_option('content_mirror_save_error_plugin_error',  ob_get_contents());
}
update_option('content_mirror_save_error_plugin_error',  '');

if (!class_exists("content_mirror")) { 

	class content_mirror {


		////////////////////////////////////////////////////////////////////////////////
		//
		// INITIALIZE OBJECT
		//
		////////////////////////////////////////////////////////////////////////////////
	
		public function __construct() {

			// Initialization stuff
			
			add_action('init', array(&$this, 'wordpress_init'));
			
			add_action('admin_init', array(&$this, 'wordpress_admin_init'));


			// add ajax functions
			
			add_action('wp_ajax_render_content_mirror_admin_form', 		array ( $this, 'render_content_mirror_admin_form_ajax' ));
			
			add_action('wp_ajax_render_content_mirror_admin_form_options', 	array ( $this, 'render_content_mirror_admin_form_options_ajax' ));
			
			add_action('wp_ajax_create_shortcode_from_form', 				array ( $this, 'create_shortcode_from_form_ajax' ));
			
			add_action('wp_ajax_content_mirror_remote_admin', 			array ( $this, 'content_mirror_remote_admin_ajax' ));	// used from local admin
			
			add_action('wp_ajax_nopriv_content_mirror_remote', 			array ( $this, 'content_mirror_remote_ajax' ));	// used from remote machine

			
			// add admin menu hook
			
			add_action('admin_menu', array ( $this, 'admin_menu' ) );
			
			
			// add shortcode
			
			add_shortcode('contentmirror', array( $this, 'handle_shortcode_content_mirror' ));
			
			add_shortcode('contentmirrorvar', array( $this, 'handle_shortcode_content_mirror_var' ));
			
			add_shortcode('contentmirrorif', array( $this, 'handle_shortcode_content_mirror_if' ));
				
		}

		
		////////////////////////////////////////////////////////////////////////////////
		//
		// MAIN INIT FUNCTIONS
		// Runs upon WordPress, admin and admin menu initialization
		//
		////////////////////////////////////////////////////////////////////////////////
		
		function wordpress_admin_init() {

			// add stylesheet to editor
			
			add_filter( 'mce_css', array( $this, 'tinyplugin_css' ));
			
			
			// add editor plugin and editor button
			
			add_filter( 'mce_external_plugins', array( $this, 'tinyplugin_register' ));
			
			add_filter( 'mce_buttons_2', array( $this, 'tinyplugin_add_button') , 0);
			
			
			// add scripts and css to the admin page
			
			wp_register_script	( 'content-mirror', 	WP_PLUGIN_URL . "/content-mirror/content-mirror-options.js" );
			
			wp_enqueue_script 	( 'content-mirror' );
	
			wp_register_style 	( 'content-mirror', 	WP_PLUGIN_URL . "/content-mirror/content-mirror.css" );
			
			wp_enqueue_style 	( 'content-mirror' );

		}
		
		public function wordpress_init () {
		
		}
		
		public function admin_menu () {
		
			add_options_page ( 'Content Mirror', 'Content Mirror', 'administrator', 'content-mirror', array ( $this, 'admin_page') );
		}


		////////////////////////////////////////////////////////////////////////////////
		//
		// RENDER ADMINPAGE
		//
		////////////////////////////////////////////////////////////////////////////////
		
		public function admin_page () {
	
			// check previligies
			
			if ( !current_user_can ( 'manage_options' )) wp_die( __('You do not have sufficient permissions to access this page.') ); 


			// See if the user has posted us some information. If they did, this hidden field will be set to 'Y'
			
			if( isset ( $_POST [ 'submit_options_hidden' ] ) && $_POST [ 'submit_options_hidden' ] == 'Y' && isset ( $_POST['update-options'] ) && wp_verify_nonce( $_POST['update-options'], 'update-options' ) ) {
					
				// save options
				$this->update_option_from_form ( 'remote_mirror' );
				
				// Put an settings updated message on the screen
				echo '<div class="updated"><p><strong>Inställningana är sparade</strong></p></div>';
		
			}
			
			
			// get stored variables
			
			$remote_mirror						= get_option('remote_mirror');
			
			$this_location						= base64_encode(admin_url('admin-ajax.php'));
			
			$this_secred						= get_option('this_secred');
			
			if ( $this_secred == '' ) {
			
				$this_secred = base64_encode(sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
				   mt_rand(0, 65535), mt_rand(0, 65535),
				   mt_rand(0, 65535),
				   mt_rand(0, 4095), 
				   bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
				   mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535) 
			    ));
			    
			    update_option( 'this_secred', $this_secred );
			}

			echo 	'<div class="wrap content_mirror_options"><form method="post" action=""><input type="hidden" name="submit_options_hidden" value="Y" />';
			
			echo 	'<div id="icon-options-general" class="icon32"><br></div><h2>Content Mirror</h2>';
			
			wp_nonce_field ( 'update-options' );
			
			echo		'<table class="form-table">
						<tr valign="top">
							<th scope="row"><label>Remote Mirror Source</label></th>
							<td>
								<label for="content_mirror_remote_mirror">
								<input type="checkbox" name="content_mirror_remote_mirror" id="content_mirror_remote_mirror" 
								value="YES"' . ( $remote_mirror	== 'YES' ? ' checked="checked"' : '' ) . '> <span style="position: relative; top: 2px; ">Enable this site as a remote mirror source</span>
								</label>
								<div></div>
								<div class="help_text">When enabled you can mirror content from this site to another site with the Content Mirror Plugin installed on both sites. On the remote site, use the 
								following location and secret to gain access to this site.</div>
								<div class="remote_site_info"><input readonly="true" value="Site location: ' . $this_location . '" /></div>
								<div class="remote_site_info"><input readonly="true" value="Site secret: ' . $this_secred . '" /></div>
								<div class="help_text" style="margin-top: 10px; "><strong>Connected Sites</strong><br/>The following sites are currently connected to this site and can mirror content without restrictions.</div>
								
								<table class="form-table small_table">
									<thead>
										<tr><th>Name</th><th>IP</th><th>User</th><th>Post-types</th><th>Status</th><th>Requests</th><th>Action</th></tr>
									</thead>
									<tbody>
										<tr><td>Ronnyes server</td><td>127.0.0.1</td><td>Admin</td><td>Post, Pages, People <span class="remote_mirror_change_post_types">[change]</span></td><td>Trying to connect but with wrong secret key</td><td>1</td><td><input type="button" name="add_site" class="button" value="Block" /><input type="button" name="add_site" class="button" value="Delete" /></td></tr>
									</tbody>
								</table>
								
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label>Remote Mirror Site</label></th>
							<td>If connected to a remote mirror site, this site can mirror information from the remote site. This site is connected to the following remote sites:
								<table class="form-table small_table">
									<thead>
										<tr><th>Name</th><th>Post-types</th><th>Status</th><th>Cache</th><th>Action</th></tr>
									</thead>
									<tbody>
										<tr><td>Ronnyes server</td><td>Post, Pages, People</td><td>Waiting for acceptance</td><td><input type="text" class="remote_mirror_cache_seconds" />s.</td><td><input type="button" name="add_site" class="button" value="Refresh" /><input type="button" name="add_site" class="button" value="Delete" /></td></tr>
									</tbody>
								</table>
							
								<ul>
									<li>No remote site is available</li>
								</ul>
								<div class="add_remote_target">
									<h4>Add new remote site</h4>
									<div id="remote_site_status">Checkeing</div>
									<div id="remote_site_form">
										<table class="form-table">
											<tr valign="top" class="row_site_name">
												<th scope="row"><label for="add_server_site_location">Site name:</label></th>
												<td id="add_server_site_location_name"></td>
											</tr>
											<tr id="content_mirror_add_remote_accept_text"><td></td><td class="help_text">Note: After you\'ve added the remote site, the remote site\'s administrator needs to accept your connection before you can use it.</td></tr>
											<tr valign="top" class="row_site_location"> 
												<th scope="row"><label for="add_server_site_location">Site location:</label></th>
												<td><input name="add_server_site_location" type="text" id="add_server_site_location" value="" class="regular-text" />
												<div class="help_text">Copy the remote site\'s Site Location and paste here.</div></td>
											</tr>
											<tr valign="top" class="row_site_secret">
												<th scope="row"><label for="add_server_site_secret">Site secret:</label></th>
												<td><input name="add_server_site_secret" type="text" id="add_server_site_secret" value="" class="regular-text" />
												<div class="help_text">Copy the remote site\'s Site Secret and paste here.</div></td>
											</tr>
											<tr valign="top" class="row_cache">
												<th scope="row"><label for="add_server_site_cache">Cache content:</label></th>
												<td><input name="add_server_site_cache" type="text" id="add_server_site_cache" value="" class="regular-text" /> minutes
												<div class="help_text">Sets how many minutes this site will cache content from the remote site. If set to zero (0) content will be accessed every page-load wich will make your site 
												a little bit slower (how much depending on the network speed between the remote site\'s server and this site\'s server). If very long, updates on content from the the remote site mirrored on this site will be deleyed to this site. A few hours is probably ok. 
												You can always clear cache by edit and save the page/post where you have put the Content Mirror.</div>
												</td>
											</tr>
										</table>
										<p class="submit"><input type="button" name="content_mirror_add_remote_site" id="content_mirror_add_remote_site" class="button-primary" value="Add This Site" /><input type="button" name="content_mirror_validate_remote_site" id="content_mirror_validate_remote_site" class="button-primary" value="Validate Remote Site" /><input type="button" name="add_site" class="button" value="Cancel" /></p>
									</div>
								</div>
								<div style="margin: 2px 0px 10px; font-size: 90%;">If you want access another remote site, install the Content Mirror Plugin on that site, enable Remote Mirror Source over there and add that site\'s location and secret here.</div>
								<input type="button" name="add_site" class="button" value="Add Remote Site" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label>Multi-site</label></th>
							<td>
								<label for="fs_schema_show_debug">
								<input type="checkbox" name="fs_schema_show_debug" id="fs_schema_show_debug" 
								value="YES"' . ( $remote_mirror	== 'YES' ? ' checked="checked"' : '' ) . '> <span style="position: relative; top: 2px; ">Enable this site as a remote mirror source</span>
								</label>
								<div></div>
								<div class="help_text">When enabled you can mirror content from this site to another site with the Content Mirror Plugin installed on both sites.<br>On the remote site, use the 
								following location and secret to gain access to this site.</div>
								
							</td>
						</tr>
					</table>
					<p class="submit"><input type="submit" name="Submit" class="button-primary" value="Save settings" /><input type="button" name="add_site" class="button" value="More..." /></p></form></div>
					<script language="javascript">jQuery(document).ready(function() { content_mirror_admin_options.init(); });</script>';
			
		}

		////////////////////////////////////////////////////////////////////////////////
		//
		// Private function: Update option from admin form
		//
		////////////////////////////////////////////////////////////////////////////////		
			
		private function update_option_from_form ( $option_name, $array = false ) {
		
			if ( ISSET ( $_POST [ $option_name ] )) {
			
				if ( is_array ( $_POST [ $option_name ] )) 
				
					update_option( $option_name, implode ( ',', $_POST[ $option_name ] ));
					
				else 
				
					update_option( $option_name, $_POST[ $option_name ] );
				
			}
			
			else delete_option( $option_name );
		}




	
		////////////////////////////////////////////////////////////////////////////////
		//
		// Recieve a remote mirror request
		//
		////////////////////////////////////////////////////////////////////////////////		
				
		public function content_mirror_remote_ajax () {
		
			$command 			= isset( $_POST[ 'command' ] )? $_POST[ 'command' ] : '';
			$secret 			= isset( $_POST[ 'secret' ] )? $_POST[ 'secret' ] : '';
			
			$response = array( 'error' => false, 'message' => '' );
			
			// first check that the ip is valid
			if ( $_SERVER['REMOTE_ADDR'] == '127.0.0.12') {
			
				$response['error'] 		= true;
				$response['message'] 	= 'Your ip ' . $_SERVER['REMOTE_ADDR'] . ' is banned from using this service.';	
			
			// else, check that the secret is ok
			} else if ( $secret != get_option('this_secred') ) {	
			
				$response['error'] 		= true;
				$response['message'] 	= 'Site Secret is invalid.';				
			
			} else {
			
				switch ( $command ) {
				
					default:
						
						$response['error'] 		= true;
						$response['message'] 	= 'I could not understand what your\'re saying.';
						break;
						
					 case 'validate':
					 	$response['content'] 	= get_current_site()->site_name . ' at ' . get_current_site()->domain;
					
					 
						break;
				
				}
			}
			
			echo json_encode($response);
			
			die();
		
		}
		
		
		
		
		////////////////////////////////////////////////////////////////////////////////
		//
		// Fetch remote mirror
		//
		////////////////////////////////////////////////////////////////////////////////		
				
		public function content_mirror_remote_admin_ajax () {
		
			$command 				= isset( $_POST[ 'command' ] )? $_POST[ 'command' ] : '';
			
			$output				= array ( 'error' => false, 'message' => '', 'flag' => '' );
			
			switch ( $command ) {
			
				case 'validate_remote':
				
					$site_location 			= isset( $_POST[ 'site_location' ] )? $_POST[ 'site_location' ] : '';
					
					$site_secret 				= isset( $_POST[ 'site_secret' ] )? $_POST[ 'site_secret' ] : '';
					
					
					// check if this is the same server?
					
					if ( $site_location == base64_encode(admin_url('admin-ajax.php'))) {
					
						$output['content']		= get_current_site()->site_name . ' at ' . get_current_site()->domain;
						
						$output['message'] 		= 'But wait, you\'re calling this server. Are you going in circles? I have to stop this from happening.';
						
						$output['flag']		= 'same_server';
					
					} else {	
					
						$output				= $this->call_remote_mirror ( array (
						
							'site_location'	=> $site_location,
							
							'site_secret'		=> $site_secret,
							
							'command'			=> 'validate'
						
						));
					}	
					
					//print_r( $fetch_result );
				
					break;
					
				default:
					
					$output['error']			= true;
					
					$output['message']			= 'No command specified in the request';
				
					break;
			}
		
			echo json_encode( $output );	
			
			die();
		
		}
		

		////////////////////////////////////////////////////////////////////////////////
		//
		// Call remote mirror
		//
		////////////////////////////////////////////////////////////////////////////////		
				
		public function call_remote_mirror( $args ) {
		
			$url				= base64_decode( $args['site_location'] );
	
			$args['secret'] 	= $args['site_secret'];
			
			$args['sender'] 	= get_current_site()->site_name . ' at ' . get_current_site()->domain . ' (' . $_SERVER['SERVER_ADDR'] . ')';
			
			$args['action'] 	= 'content_mirror_remote_mirror_request';
			
			unset($args['location']); 
			
			return $this->curl( $url, $args );
		
		}

		
		

		////////////////////////////////////////////////////////////////////////////////
		//
		// Private curl
		//
		////////////////////////////////////////////////////////////////////////////////		
			
		private function curl ( $url, $post_fields = array() ) {

			// init curl
			
			$response						= array ( 'error' => false, 'message' => '', 'content' => '' );
			
			$ch 							= curl_init();
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			
			curl_setopt($ch, CURLOPT_URL, $url );
			
			$post_fields 					= http_build_query( $post_fields );
			
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields); 
			
			$curl_response 				= curl_exec($ch);
			
			$curl_info 					= curl_getinfo($ch);
			
			curl_close($ch);
			
			$http_code					= isset ( $curl_info ['http_code'] ) ? $curl_info ['http_code'] : '0';
			
			if ( $http_code != '200' ) {
			
				$response['error']			= true;
			
				$response['message'] 		= 'Could not connect to the remote server. Is the Site Location correct?';
			
			} else {
			
				$json_response				= json_decode($curl_response);
				
				if ( !$json_response ) {
				
					$response['error']		= true;
			
					$response['message'] 	= 'Could not get a valid response from the remote server. Is the Site Location correct?';				
				
				} else {
				
					if ( $json_response->error == true ) {
					
						$response['error']		= true;
					
						$response['message']  	= 'The remote server responded with this error-message: "' . $json_response->message . '"';
					
					} else {
				
						$response['content'] 	= property_exists($json_response, 'content') ? $json_response->content : '';
					}
				}
			}
			
			return $response;
		}		


		////////////////////////////////////////////////////////////////////////////////
		//
		// ADD TINY MCE PLUGIN AND BUTTON
		//
		////////////////////////////////////////////////////////////////////////////////
		
		public function tinyplugin_register ( $plugin_array ) {
			//$plugin_array["content_mirror"] = WP_PLUGIN_URL . "/" . dirname( plugin_basename(__FILE__) ) . "/content-mirror-editor.js";
			$plugin_array["content_mirror"] = WP_PLUGIN_URL . "/content-mirror/content-mirror-editor.js";
			return $plugin_array;		
		}
		
		public function tinyplugin_add_button ( $buttons ) {
   			array_push($buttons, 'separator', 'content_mirror');
			return $buttons;		
		}
		
		public function tinyplugin_css( $wp ) {
			//$wp .= ',' . WP_PLUGIN_URL . "/" . dirname( plugin_basename(__FILE__) ) . "/content-mirror.css" ;
			$wp .= ',' . WP_PLUGIN_URL . "/content-mirror/content-mirror.css" ;
			return $wp;
		}
		
		public function tiny_mce_version ( $version ) {
			return ++$version;	
		}
		
		
		
		////////////////////////////////////////////////////////////////////////////////
		//
		// HANDLE SHORTCODE CONTENT MIRROR
		//
		////////////////////////////////////////////////////////////////////////////////
				
		function handle_shortcode_content_mirror ( $attr, $content = null ) {
	
			global $wpdb; 
			
			$defaults = array (
				'site' 				=> '',
				'post_type'			=> 'post',
				'item'				=> '0'
			);
			
			$r = wp_parse_args( $attr, $defaults );
			
			global $content_is_mirror;
			global $content_mirror_original_blogid;
			global $content_mirror_params;
			
			$content_is_mirror = true;
			$content_mirror_params = $r;
			$switched_blog = false;
			
			if ( $r['site'] != $wpdb->blogid ) {
				
				$content_mirror_original_blogid = $wpdb->blogid;
				switch_to_blog ( $r['site'] );
				$switched_blog = true;
			}

			$post_item = get_post( $r['item'] );
			$post_content = '';

			if ( !empty ( $post_item )) {
				$post_content = $post_item->post_content;
				$post_content = apply_filters('the_content', $post_content);
				$post_content = '<div class="content_mirror content_mirror_site_' . $r['site'] . ' content_mirror_post_type_' . $r['post_type'] . ' content_mirror_item_' . $r['item'] . '">' . str_replace(']]>', ']]&gt;', $post_content) . '</div>';
			}

			if ( $switched_blog !== false ) restore_current_blog();
			
			$post_content = apply_filters ('content_mirror_output', $post_content, $r['site'], $r['post_type'], $r['item'], $post_item );
			
			unset( $content_is_mirror );
			unset( $content_mirror_original_blogid );
			unset( $content_mirror_params );
			
			return $post_content;			
		}



		////////////////////////////////////////////////////////////////////////////////
		//
		// HANDLE SHORTCODE CONTENT MIRROR VAR
		//
		////////////////////////////////////////////////////////////////////////////////
				
		function handle_shortcode_content_mirror_var ( $attr, $content = null ) {
	
			global $wpdb; 
			global $content_mirror_params;
			
			$defaults = array (
				'name' 				=> ''
			);
			
			$r = wp_parse_args( $attr, $defaults );
			
			if ( $r['name'] != '' && isset( $content_mirror_params[$r['name']] )) return $content_mirror_params[$r['name']];
			
			return '';
			
		}
		
		
		
		////////////////////////////////////////////////////////////////////////////////
		//
		// HANDLE SHORTCODE CONTENT MIRROR VAR
		//
		////////////////////////////////////////////////////////////////////////////////
				
		function handle_shortcode_content_mirror_if ( $attr, $content = null ) {
	
			global $wpdb; 
			global $content_mirror_params;
			
			$defaults = array (
				'name' 				=> '',
				'compare'				=> 'equal',
				'value'				=> ''
			);
			
			$r = wp_parse_args( $attr, $defaults );
			
			if ( $r['name'] != '' && isset( $content_mirror_params[$r['name']] ) ) {
			
				switch ( $r['compare'] ) {
				
					case 'equal':
						if ( $content_mirror_params[$r['name']] == $r['value'] ) return $content;
						break;
						
					case 'not':
						if ( $content_mirror_params[$r['name']] != $r['value'] ) return $content;
						break;
						
				}
			}
			
			return '';

		}


		////////////////////////////////////////////////////////////////////////////////
		//
		// RENDER CONTENT MIRROR ADMIN FORM
		// Called from editor by javascript ajax
		// Viewed inside a thickbox
		//
		////////////////////////////////////////////////////////////////////////////////
	
		function render_content_mirror_admin_form () {
			global $wpdb; 
			wp_enqueue_script ( 'jquery' );
			wp_enqueue_script ( 'tiny-mce-popup', site_url() . '/' . WPINC . '/js/tinymce/tiny_mce_popup.js' );
			//wp_enqueue_script ( 'content-mirror', WP_PLUGIN_URL . "/" . dirname( plugin_basename(__FILE__) ) . "/content-mirror-options.js" );
			wp_enqueue_script ( 'content-mirror', WP_PLUGIN_URL . "/content-mirror/content-mirror-options.js" );?>
		
			<html><head>
				<link rel="stylesheet" type="text/css" href="<?php echo '/wp-content/plugins/' . str_replace( '.php', '.css', plugin_basename(__FILE__)) ?>" />
				<link rel="stylesheet" type="text/css" href="<?php echo WP_PLUGIN_URL . "/content-mirror/content-mirror.css" ?>" /><?php
				do_action('wp_head');?>
			</head>
			<body id="content_mirror_edit">
				<h3 class="mirror-title">Select what content you want to mirror</h3>
				
				<div class="selecters">
					<?php
					
					if ( is_multisite() == true ) { ?>
						<div class="row">
							<label for="site">Site:</label>
							<select name="site" id="site"><?php
							
								$blogs = $wpdb->get_results("SELECT blog_id FROM $wpdb->blogs", ARRAY_A);
								foreach ( $blogs as $blog ) {
									$current_blog_details = get_blog_details( array( 'blog_id' => $blog['blog_id'] ) );
									if ( $current_blog_details && $current_blog_details->blogname ) {
										echo '<option value="' . $blog['blog_id'] . '">' . $current_blog_details->blogname . '</option>';
									}
								}
							?>
								
							</select>
							<div class="clear"></div>
						</div><?php
					} ?>
					<div class="row">
						<label for="post-type">Post type:</label>
							<select name="post-type" id="post-type"><?php
						
							global $wp_post_types;
							$post_types=get_post_types( array( 'public' => true ), 'names'); 
  							foreach ($post_types  as $post_type ) {
  								if ( post_type_supports ( $post_type, 'editor' ) ) {
  									echo '<option value="' . $post_type . '">' . ucwords( $post_type ) . '</option>';
  								}
  							}?>
						</select>
						<div class="clear"></div>
					</div>				
					<div class="row">
						<label for="site">Item:</label>
						<select name="item" id="item">
							<option></option>
						</select>
						<div class="clear"></div>
					</div>
				</div>
				<div class="row">
					<label class="simple">Preview Original:</label>
				</div>
				<div id="preview_area" class="row preview">
				</div>
				<div id="form_buttons">
					<button class="button" id="select_button" type="button">Update</button>
					<button class="button" id="cancel_button" type="button">Cancel</button>
				</div>
				
				<script language="javascript">
					ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
					post_id = '<?php echo ( isset($_GET['post_id']) && $_GET['post_id'] != '' ) ? $_GET['post_id'] : '0'?>';
					jQuery(document).ready(function() { tinyMCEPopup.onInit.add(content_mirror_options.init, content_mirror_options); });
				</script>
			</body></html><?php
		}
		

		////////////////////////////////////////////////////////////////////////////////
		//
		// GET CONTENT USED IN THE MIRROR ADMIN FORM
		// Options and so on
		//
		////////////////////////////////////////////////////////////////////////////////
			
		function render_content_mirror_admin_form_options_ajax () {
		
			global $wpdb; 
			
			$content = ( isset($_POST['content']) && $_POST['content'] != '' ) ? $_POST['content'] : 'itemlist';
			$site = ( isset($_POST['site']) && $_POST['site'] != '' ) ? $_POST['site'] : '1';
			$posttype = ( isset($_POST['posttype']) && $_POST['posttype'] != '' ) ? $_POST['posttype'] : '1';
			$item = ( isset($_POST['item']) && $_POST['item'] != '' ) ? $_POST['item'] : '1';
			$post_id = ( isset($_POST['post_id']) && $_POST['post_id'] != '' ) ? $_POST['post_id'] : '0';
			
			switch ( $content ) {
				
				case 'itemlist':
					$current_blog_id = $wpdb->blogid;
					$wpdb->set_blog_id( $site );
					$post_items = get_posts ( array ( 'post_type' => $posttype, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ));
					foreach ( $post_items as $post_item ) {
						if ( $post_item->ID == $post_id && $current_blog_id == $site ) {						
							$item_list_option = array ( 'value' => '0', 'title' => $post_item->post_title, 'html_class' => 'same_item' );
						} else {
							$item_list_option = array ( 'value' => $post_item->ID, 'title' => $post_item->post_title, 'html_class' => '' );
						}
						$item_list_option = apply_filters ('content_mirror_list_item', $item_list_option, $post_item );
						if ( $item_list_option ) {
							echo '<option value="' . $item_list_option['value'] . '" class="' . $item_list_option['html_class'] . '">' . $item_list_option['title'] . '</option>';
						}
					}
					$wpdb->set_blog_id( $current_blog_id );
					break;
				case 'preview':
					if ( $item == '0' ) {
						echo '<h1 class="preview_error">This is the same page that you\'re editing</h1>';
					} else {
						$current_blog_id = $wpdb->blogid;
						$wpdb->set_blog_id( $site );
						$post_item = get_post( $item );
						if ( $post_item ) {
							echo $post_item->post_content == '' ? '<h1 class="preview_error">Post is empty</h1>' : wpautop ( $post_item->post_content );
						} else {
							echo '<h1 class="preview_error">Cannot find post</h1>';
						} 
						$wpdb->set_blog_id( $current_blog_id );
					}
					break;
			}
			die();
		}
		
		////////////////////////////////////////////////////////////////////////////////
		//
		// CREATE SHORTCODE FROM FORM
		// Called from admin editor by javascript ajax 
		// Creating the shortcode based on the input
		//
		////////////////////////////////////////////////////////////////////////////////

		function create_shortcode_from_form () {
		
			global $wpdb;
			
			
		}
		

		////////////////////////////////////////////////////////////////////////////////
		//
		// INTERNAL FUNCTIONS
		// And small intermediate ones
		//
		////////////////////////////////////////////////////////////////////////////////
		
		// Ajax intermediate functions
		function render_content_mirror_admin_form_ajax () { $this->render_content_mirror_admin_form (); die();}
		function create_shortcode_from_form_ajax () { $this->create_shortcode_from_form(); die(); }

	} 
	
} //End Class


if (class_exists("content_mirror")) { $content_mirror = new content_mirror(); }
echo get_option('content_mirror_save_error_plugin_error');
?>