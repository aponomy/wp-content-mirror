/*///////////////////////////////////////////////////////

	Plugin Name: Content Mirror
	Plugin URI: http://klasehnemark.com
	Description:  
	Author: Klas Ehnemark
	Version: 1.0
	Author URI: http://klasehnemark.com
	
	Copyright (C) 2011 Klas Ehnemark (http://klasehnemark.com)


	CONTENT MIRROR OPTIONS OBJECT
	
////////////////////////////////////////////////////////*/

content_mirror_options = {
					
	local_ed : 'ed',
	current_shortcode : '',
	previous_site : '0',
	previous_posttype : '0',
	previous_item : '0',
	
	init: function ( ed ) {
		content_mirror_options.local_ed = ed;
		tinyMCEPopup.resizeToInnerSize();
		if ( tinyMCEPopup.getWindowArg('current_shortcode') != null ) content_mirror_options.current_shortcode = tinyMCEPopup.getWindowArg('current_shortcode');
		
		jQuery('#site').change( function() { if ( content_mirror_options.previous_site == '0' ) content_mirror_options.reload_items(); });
		jQuery('#post-type').change( function() { content_mirror_options.reload_items(); });
		jQuery('#item').change( function() { content_mirror_options.load_preview(); });
		jQuery('#select_button').bind( "click", function() { content_mirror_options.select_mirror(); });
		jQuery('#cancel_button').bind( "click", function() { tinyMCEPopup.close(); });
		
		if ( content_mirror_options.current_shortcode == '' ) {
			jQuery('#select_button').html('Insert');
		} else {
			var current_shortcode_split = content_mirror_options.current_shortcode.split(' ');
			for (var i = 0; i < current_shortcode_split.length; i++) {
				part_split = current_shortcode_split[i].split('=');
				if ( part_split.length > 1 ) {
					switch ( part_split[0] ) {
						case 'site': content_mirror_options.previous_site = part_split[1]; break;
						case 'posttype': content_mirror_options.previous_posttype = part_split[1]; break;
						case 'item': content_mirror_options.previous_item = part_split[1]; break; 
					}
				}
			}
			if ( content_mirror_options.previous_site != '0' ) {
				jQuery('#site').val( content_mirror_options.previous_site );
				content_mirror_options.previous_site = '0';
			}
			if ( content_mirror_options.previous_posttype != '0' ) {
				jQuery('#post-type').val( content_mirror_options.previous_posttype );
				content_mirror_options.previous_posttype = '0';
			}
		}
		content_mirror_options.reload_items();
		
		// Add Image resize function to jQuery
		jQuery.fn.resize = function(max_size) {
			m = Math.ceil;
			if (max_size == undefined) {
				max_size = 200;
			}
			h=w= max_size;
			jQuery(this).each(function() {
				image_h = jQuery(this).height();
				image_w = jQuery(this).width();
				if ( image_h > max_size || image_w > max_size ) {
					if (image_h > image_w) {
						w = m(image_w / image_h * max_size);
					} else {
						h = m(image_h / image_w * max_size);
					}
					jQuery(this).css({height:h,width:w});
			    	}
			})
		};

	},
	
	reload_items : function () {
		jQuery('#item').html('<option value="0">Loading...</option>');
		jQuery.ajax({
			url: ajaxurl,
			type: "POST",
			dataType: 'html',
			data: ({ action : 'render_content_mirror_admin_form_options', content: 'itemlist', post_id: post_id, site : jQuery('#site').val(), posttype : jQuery('#post-type').val(), item : jQuery('#item').val() }),
			context: document.body,
			success: function( html, textStatus) {
				jQuery('#item').html(html);
				if ( content_mirror_options.previous_item != '0' ) {
					jQuery('#item').val( content_mirror_options.previous_item );
					content_mirror_options.previous_item = '0';
				}
				content_mirror_options.load_preview();
			},
			error: function ( jqXHR, textStatus, errorThrown )  {
				alert(textStatus + ' ' + errorThrown );
			}
		});
	},
	
	load_preview : function () {
		jQuery('#preview_area').html('<h3 class="preview_status">Loading...</h3>')
		jQuery.ajax({
			url: ajaxurl,
			type: "POST",
			dataType: 'html',
			data: ({ action : 'render_content_mirror_admin_form_options', content: 'preview', post_id: post_id, site : jQuery('#site').val(), posttype : jQuery('#post-type').val(), item : jQuery('#item').val() }),
			context: document.body,
			success: function( html, textStatus) {
				jQuery('#preview_area').html(html);
				jQuery("#preview_area img").resize(390);
			},
			error: function ( jqXHR, textStatus, errorThrown )  {
				alert(textStatus + ' ' + errorThrown );
			}
		});						
	},
	
	
	select_mirror : function () {
		if ( jQuery('#item').val() == '0' ) {
			alert("You cannot mirror the same page as you're currently editing."); 
		} else {
			// tinyMCEPopup.execCommand('mceRemoveNode', false, null);	// this line makes strange things happen						
			output = '[contentmirror ' + 'site=' + jQuery('#site').val() + ' posttype=' + jQuery('#post-type').val() + ' item=' + jQuery('#item').val() + ']';
			// not using content : content_mirror_options.local_ed.selection.getContent()
			tinyMCEPopup.execCommand('mceReplaceContent', false, output);
			content_mirror_options.local_ed.execCommand('mceSetContent', false, content_mirror_options.local_ed.getContent());
			tinyMCEPopup.close();
		}
	}
}


content_mirror_admin_options = {
					
	init: function () {
	
		jQuery('#content_mirror_validate_remote_site').click( function() { content_mirror_admin_options.validate_remote_site (); });
		jQuery('#content_mirror_add_remote_site').click( function() { content_mirror_admin_options.close_site_dialogue ( true ); });
		
		
		jQuery('.remote_mirror_change_post_types').click( function() { content_mirror_admin_options.remote_mirror_change_post_types ( this ); });
	
		
		
		content_mirror_admin_options.open_add_site_dialogue();
		
	
	},
	
	open_add_site_dialogue : function() {
	
		jQuery('#content_mirror_add_remote_site').hide();
		jQuery('#content_mirror_validate_remote_site').show();
		jQuery('#content_mirror_add_remote_accept_text').hide();
		jQuery('.row_site_name').hide();
		jQuery('.row_cache').hide();
		jQuery('.row_site_location').show();
		jQuery('.row_site_secret').show();
		
		// add new
		jQuery('#add_server_site_cache').val('60');
	},
	
	close_site_dialogue : function( add ) {
	
		if ( add === true ) {
		
			
		
		
		}
	},
	
	remote_mirror_change_post_types : function( el ) {
	
	
	},
	
	
	
	validate_remote_site : function() {
		jQuery('#add_server_site_cache').val(parseInt(jQuery('#add_server_site_cache').val())); 
		if ( jQuery('#add_server_site_cache').val() == 'NaN' ) jQuery('#add_server_site_cache').val('');
		if ( jQuery('#add_server_site_location').val() == '' ) {
			jQuery('#remote_site_status').html('Site Location field cannot be empty.').show();
		} else if ( jQuery('#add_server_site_secret').val() == '' ) {
			jQuery('#remote_site_status').html('Site Secret field cannot be empty.').show();
		} else {
			jQuery('#remote_site_form').hide();
			jQuery('#remote_site_status').html('Validating remote site. Please wait...').show();
			
			content_mirror_admin_options.ajax ( { action : 'content_mirror_remote_admin', command: 'validate_remote', site_location: jQuery('#add_server_site_location').val(), site_secret: jQuery('#add_server_site_secret').val() }, 
			
				function( jsn ) {
					jQuery('#remote_site_status').html('Remote Mirror Site has been successfully validated. ' + jsn.message ).show();
					jQuery('#add_server_site_location_name').html(jsn.content);
					jQuery('#content_mirror_add_remote_site').show();
					jQuery('#content_mirror_validate_remote_site').hide();
					jQuery('.row_site_name').show();
					jQuery('.row_cache').show();
					jQuery('#remote_site_form').show();
					jQuery('#content_mirror_add_remote_accept_text').show();
					jQuery('.row_site_location').hide();
					jQuery('.row_site_secret').hide();	
				}, 
				
				function( message ) {
					jQuery('#remote_site_status').html(message);
					jQuery('#remote_site_form').show();
				}
			);
			
			/*jQuery.ajax({
				url: ajaxurl,
				type: "POST",
				dataType: 'html',
				data: ({ action : 'content_mirror_remote_admin', command: 'validate_remote', site_location: jQuery('#add_server_site_location').val(), site_secret: jQuery('#add_server_site_secret').val() }),
				timeout: 20000,
				success: function( response, textStatus) {
					if ( response == null ) jQuery('#remote_site_status').html('Could not add the remote mirror at this point. Something is wrong with this server configuration.').show();
					else {
						valid_json = true;
						try {
							jsn = jQuery.parseJSON(response);
						} catch(x) {
							valid_json = false;
						}
						if ( valid_json == false ) {
							jQuery('#remote_site_status').html('Could not add the remote mirror at this point. Something is wrong with this server configuration. ' + response ).show();
						} else {
							if ( jsn.error == true ) jQuery('#remote_site_status').html(jsn.message);
							else {
								jQuery('#remote_site_status').html('Remote Mirror Site has been successfully validated. ' + jsn.message ).show();
								
								jQuery('#add_server_site_location_name').html(jsn.content);
								jQuery('#content_mirror_add_remote_site').show();
								jQuery('#content_mirror_validate_remote_site').hide();
								jQuery('.row_site_name').show();
								jQuery('.row_cache').show();
								jQuery('.row_site_location').hide();
								jQuery('.row_site_secret').hide();								
								
							}
						}
					}
					jQuery('#remote_site_form').show();
				},
				error: function ( jqXHR, textStatus, errorThrown )  {
					jQuery('#remote_site_status').html('An error occured when recieving data from this server: (' + textStatus + ') ' + errorThrown).show();
					jQuery('#remote_site_form').show();
				}
			});*/
		}
	
	},
	
	
	
	ajax : function ( data,  fn_success, fn_error ) {
	
		//data.nonce: fsschemavars.nonce;
	
		jQuery.ajax({
		
			type: "POST", url: ajaxurl, dataType: 'html', data: (data), context: document.body,
			
			timeout: 20000,  // 20 seconds
			
			success: function( response ) { 
				if ( response == null ) fn_error('Could not add the remote mirror at this point. Something is wrong with this server configuration.');
				else {
					valid_json = true;
					try {
						jsn = jQuery.parseJSON(response);
					} catch(x) {
						valid_json = false;
					}
					if ( valid_json == false ) {
						fn_error('Could not add the remote mirror at this point. Something is wrong with this server configuration. ' + response );
					} else {
						if ( jsn.error == true ) fn_error(jsn.message);
						else {
							fn_success (jsn); 
						}
					}
				}
			},
			
			error: function ( jqXHR, textStatus, errorThrown )  { 
				fn_error('An error occured when recieving data from this server: (' + textStatus + ') ' + errorThrown);
			}
			
		});      
     }
}